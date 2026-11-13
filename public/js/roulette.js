document.addEventListener('DOMContentLoaded', () => {
    const room = document.querySelector('[data-roulette-room]');

    if (!room) {
        return;
    }

    const canvas = room.querySelector('[data-roulette-canvas]');
    const spinButton = room.querySelector('[data-roulette-spin]');
    const viewers = room.querySelector('[data-roulette-viewers]');
    const viewerCount = room.querySelector('[data-roulette-viewer-count]');
    const statusLabel = room.querySelector('[data-roulette-status-label]');
    const liveDot = room.querySelector('[data-roulette-live-dot]');
    const result = room.querySelector('[data-roulette-result]');
    const winnerNode = room.querySelector('[data-roulette-winner]');
    const phraseNode = room.querySelector('[data-roulette-phrase]');
    const configurationPanel = room.querySelector('[data-roulette-config]');
    const closeRoomForm = room.querySelector('[data-roulette-close-room]');
    const repeatRoomForm = room.querySelector('[data-roulette-repeat-room]');
    const failureNode = room.querySelector('[data-roulette-failure]');

    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const context = canvas.getContext('2d');
    if (!context) {
        return;
    }

    const stateUrl = room.dataset.stateUrl;
    const spinUrl = room.dataset.spinUrl;
    const csrf = room.dataset.csrf;
    const roomId = room.dataset.roomId;
    const viewerId = Number(room.dataset.viewerId || 0);
    let lastState = null;
    let serverOffsetMs = 0;
    let frame = null;
    let pollTimer = null;
    let requestBusy = false;
    let spinBusy = false;
    let currentRotation = 0;
    let lastWheelSignature = '';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let visibleSegments = [];
    const wheelWrapper = canvas.closest('.roulette-wheel-wrap') || canvas.parentElement;
    const tooltip = document.createElement('div');
    tooltip.setAttribute('role', 'tooltip');
    tooltip.setAttribute('aria-hidden', 'true');
    Object.assign(tooltip.style, {
        position: 'absolute', zIndex: '30', pointerEvents: 'none',
        padding: '9px 13px', borderRadius: '9px',
        border: '1px solid rgba(255, 184, 76, .75)',
        background: '#0c131e', color: '#fff',
        font: '800 15px/1.25 system-ui, sans-serif',
        boxShadow: '0 8px 24px rgba(0,0,0,.65)',
        whiteSpace: 'nowrap', maxWidth: 'min(240px, 85vw)',
        overflow: 'hidden', textOverflow: 'ellipsis',
        opacity: '0', visibility: 'hidden',
        transition: 'opacity .12s ease'
    });
    wheelWrapper.appendChild(tooltip);
    let lastHighlightFrame = 0;
    const highlightPulse = () => reducedMotion.matches ? .45 : .5 + .5 * Math.sin(performance.now() * Math.PI * 2 / 6000);
    const animateMySegment = (timestamp) => {
        if (viewerId > 0 && !document.hidden && !reducedMotion.matches &&
            timestamp - lastHighlightFrame >= 90 &&
            (lastState?.wheel || []).some(candidate => Number(candidate.user_id) === viewerId && Number(candidate.tickets) > 0)) {
            lastHighlightFrame = timestamp;
            drawWheel(lastState.wheel, true);
        }
        requestAnimationFrame(animateMySegment);
    };
    if (viewerId > 0) requestAnimationFrame(animateMySegment);

    const statusLabels = {
        active: 'Preparando',
        spinning: 'Girando',
        completed: 'Finalizada',
        closed: 'Cerrada',
        expired: 'Caducada',
        failed: 'Incidencia',
    };

    const adjustedNow = () => Date.now() + serverOffsetMs;

    const updateServerClock = (state) => {
        const serverTime = Date.parse(state?.server_time || '');
        if (!Number.isNaN(serverTime)) {
            serverOffsetMs = serverTime - Date.now();
        }
    };

    const colorForIndex = (index, count) => {
        const base = 25;
        const step = count > 1 ? 250 / count : 0;
        const hue = (base + (index * step)) % 360;
        return `hsl(${hue} 58% 39%)`;
    };

    const hideTooltip = () => {
        tooltip.style.opacity = '0';
        tooltip.style.visibility = 'hidden';
    };

    const showTooltip = (event, label) => {
        const rect = wheelWrapper.getBoundingClientRect();
        tooltip.textContent = label;
        tooltip.style.visibility = 'visible';
        tooltip.style.opacity = '1';
        const x = event.clientX - rect.left + 14;
        const y = event.clientY - rect.top - 42;
        tooltip.style.left = `${Math.max(4, Math.min(x, rect.width - tooltip.offsetWidth - 4))}px`;
        tooltip.style.top = `${Math.max(4, Math.min(y, rect.height - tooltip.offsetHeight - 4))}px`;
    };

    const findSegmentFromPointer = (event) => {
        if (!visibleSegments.length) return null;
        const rect = canvas.getBoundingClientRect();
        const dx = event.clientX - (rect.left + rect.width / 2);
        const dy = event.clientY - (rect.top + rect.height / 2);
        const distance = Math.hypot(dx, dy);
        if (distance > rect.width * (canvas.width / 2 - 10) / canvas.width || distance < rect.width * 0.08) return null;
        const full = Math.PI * 2;
        let angle = Math.atan2(dy, dx) - currentRotation * Math.PI / 180;
        angle = ((angle + Math.PI / 2) % full + full) % full - Math.PI / 2;
        return visibleSegments.find(segment => angle >= segment.begin && angle < segment.end) || null;
    };

    const drawEmptyWheel = () => {
        const size = canvas.width;
        const center = size / 2;
        const radius = center - 10;

        context.clearRect(0, 0, size, size);
        context.beginPath();
        context.arc(center, center, radius, 0, Math.PI * 2);
        context.fillStyle = '#151c26';
        context.fill();
        context.lineWidth = 4;
        context.strokeStyle = 'rgba(255, 138, 0, .35)';
        context.stroke();

        context.fillStyle = '#8492a4';
        context.font = '700 23px system-ui, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText('Sin papeletas', center, center - 76);
    };

    const drawWheel = (wheel, force = false) => {
        const candidates = Array.isArray(wheel) ? wheel.filter((candidate) => Number(candidate.tickets) > 0) : [];
        const signature = JSON.stringify(candidates.map((candidate) => [candidate.user_id, candidate.nick, candidate.tickets]));

        if (signature === lastWheelSignature && !force) {
            return;
        }
        lastWheelSignature = signature;

        if (candidates.length === 0) {
            visibleSegments = [];
            drawEmptyWheel();
            return;
        }

        const totalTickets = candidates.reduce((sum, candidate) => sum + Number(candidate.tickets || 0), 0);
        const size = canvas.width;
        const center = size / 2;
        const radius = center - 10;
        const outerRadius = radius;
        const textRadius = radius * .67;
        let cursor = -Math.PI / 2;

        context.clearRect(0, 0, size, size);
        visibleSegments = [];

        candidates.forEach((candidate, index) => {
            const fraction = Number(candidate.tickets) / totalTickets;
            const arc = fraction * Math.PI * 2;
            const end = cursor + arc;
            const isMine = viewerId > 0 && Number(candidate.user_id) === viewerId;
            const pulse = isMine ? highlightPulse() : 0;

            context.save();
            if (isMine) {
                context.shadowColor = '#9cfbff';
                context.shadowBlur = 5 + pulse * 9;
            }
            context.beginPath();
            context.moveTo(center, center);
            context.arc(center, center, outerRadius, cursor, end);
            context.closePath();
            context.fillStyle = colorForIndex(index, candidates.length);
            context.fill();
            if (isMine) {
                context.fillStyle = `rgba(167, 247, 255, ${0.09 + pulse * .22})`;
                context.fill();
                context.shadowColor = '#9cfbff';
                context.shadowBlur = 6 + pulse * 11;
                context.lineWidth = 2 + pulse * 1.5;
                context.strokeStyle = '#62dff1';
                context.stroke();
            }
            context.restore();
            if (!isMine) {
                context.lineWidth = 2;
                context.strokeStyle = '#0b1017';
                context.stroke();
            }

            const midpoint = cursor + (arc / 2);
            const x = center + Math.cos(midpoint) * textRadius;
            const y = center + Math.sin(midpoint) * textRadius;
            const label = String(candidate.nick || 'Jugador');
            const tickets = Number(candidate.tickets || 0);
            const availableWidth = Math.max(5, 2 * textRadius * Math.sin(Math.min(arc, Math.PI) / 2) * .85);
            const fontSize = Math.max(7, Math.min(20, availableWidth * .85));

            context.save();
            context.translate(x, y);
            let textRotation = midpoint + Math.PI / 2;
            if (midpoint > Math.PI / 2 && midpoint < (Math.PI * 3) / 2) {
                textRotation += Math.PI;
            }
            context.rotate(textRotation);
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.shadowColor = isMine ? '#00eaff' : 'rgba(0, 0, 0, .6)';
            context.shadowBlur = isMine ? 3 + 4 * pulse : 3;
            context.fillStyle = '#fff';
            context.font = `${isMine ? 900 : 800} ${fontSize}px system-ui, sans-serif`;
            // Nunca omitir el nombre, aunque la porción sea muy estrecha.
            context.fillText(label, 0, fontSize > 11 ? -5 : 0, radius * .67);
            if (fontSize > 11 && arc * textRadius > 24) {
                context.fillStyle = 'rgba(255,255,255,.85)';
                context.font = `700 ${Math.max(9, fontSize - 5)}px system-ui, sans-serif`;
                context.fillText(`${tickets} ${tickets === 1 ? 'papeleta' : 'papeletas'}`, 0, 10, radius * .67);
            }
            context.restore();
            visibleSegments.push({ begin: cursor, end, label });
            cursor = end;
        });

        context.beginPath();
        context.arc(center, center, outerRadius, 0, Math.PI * 2);
        context.lineWidth = 5;
        context.strokeStyle = 'rgba(235, 241, 247, .16)';
        context.stroke();
    };

    const setRotation = (degrees) => {
        currentRotation = Number(degrees) || 0;
        canvas.style.transform = `rotate(${currentRotation}deg)`;
    };

    const easeOutQuint = (value) => 1 - Math.pow(1 - value, 5);

    const syncSpinAnimation = (state) => {
        if (frame) {
            cancelAnimationFrame(frame);
            frame = null;
        }

        const finalRotation = Number(state.final_rotation || 0);
        const start = Date.parse(state.spin_started_at || '');
        const end = Date.parse(state.spin_ends_at || '');

        if (state.status === 'completed') {
            setRotation(finalRotation || currentRotation);
            return;
        }

        if (state.status !== 'spinning' || Number.isNaN(start) || Number.isNaN(end) || end <= start) {
            if (state.status === 'active') {
                setRotation(0);
            }
            return;
        }

        const render = () => {
            const now = adjustedNow();

            if (now <= start) {
                setRotation(0);
                frame = requestAnimationFrame(render);
                return;
            }

            const progress = Math.min(1, Math.max(0, (now - start) / (end - start)));
            setRotation(finalRotation * easeOutQuint(progress));

            if (progress < 1) {
                frame = requestAnimationFrame(render);
            } else {
                frame = null;
            }
        };

        frame = requestAnimationFrame(render);
    };

    const updateViewers = (state) => {
        const people = Array.isArray(state.viewers) ? state.viewers : [];

        if (viewerCount) {
            viewerCount.textContent = String(people.length);
        }

        if (viewers) {
            viewers.replaceChildren(...people.map((person) => {
                const tag = document.createElement('span');
                tag.textContent = person.nick || 'Usuario';
                return tag;
            }));
        }
    };

    const updateStatus = (state) => {
        if (statusLabel) {
            statusLabel.textContent = statusLabels[state.status] || state.status || 'Sala';
        }

        if (liveDot) {
            liveDot.classList.toggle('is-live', state.status === 'active');
            liveDot.classList.toggle('is-spinning', state.status === 'spinning');
        }

        if (spinButton) {
            const available = Array.isArray(state.wheel)
                && state.wheel.some((candidate) => Number(candidate.tickets) > 0);
            spinButton.disabled = spinBusy || !state.can_configure || !available;
            spinButton.hidden = state.status !== 'active';

            const small = spinButton.querySelector('small');
            if (small && Array.isArray(state.wheel)) {
                const total = state.wheel.reduce((sum, candidate) => sum + Number(candidate.tickets || 0), 0);
                small.textContent = `${total} papeletas en juego`;
            }
        }

        if (configurationPanel) {
            configurationPanel.hidden = !state.can_configure;
        }

        if (closeRoomForm) {
            // Una tirada iniciada no puede cancelarse a mitad: todos los
            // espectadores deben terminar con el mismo ganador.
            closeRoomForm.hidden = !(state.can_control && state.status === 'active' && state.locks_event);
        }

        if (repeatRoomForm) {
            repeatRoomForm.hidden = !state.can_repeat;
        }

        if (failureNode) {
            if (state.failure_reason) {
                failureNode.hidden = false;
                failureNode.textContent = `⚠ ${state.failure_reason}`;
            } else {
                failureNode.hidden = true;
                failureNode.textContent = '';
            }
        }
    };

    const showResult = (state) => {
        const winner = state.winner;
        if (!result || !winner) {
            if (result && state.status !== 'completed') {
                result.hidden = true;
            }
            return;
        }

        result.hidden = false;
        if (winnerNode) {
            winnerNode.textContent = winner.nick || 'Ganador';
        }
        if (phraseNode) {
            phraseNode.textContent = winner.phrase || '';
        }

        room.querySelectorAll('[data-roulette-candidate-user-id]').forEach((candidateNode) => {
            candidateNode.classList.toggle(
                'is-winner',
                Number(candidateNode.dataset.rouletteCandidateUserId) === Number(winner.user_id),
            );
        });
    };

    const launchWinnerCelebration = (state) => {
        if (!state?.winner?.is_me || !state?.celebrate_winner) {
            return;
        }

        const storageKey = `newslot-roulette-celebrated-${roomId}`;
        try {
            if (sessionStorage.getItem(storageKey) === '1') {
                return;
            }
            sessionStorage.setItem(storageKey, '1');
        } catch {
            // Si sessionStorage no está disponible, la celebración sigue funcionando.
        }

        const overlay = document.createElement('div');
        overlay.className = 'roulette-winner-celebration';
        overlay.innerHTML = `
            <div class="roulette-winner-celebration__card">
                <small>🎯 EL AZAR HA ENCONTRADO A SU VÍCTIMA</small>
                <h2></h2>
                <p></p>
                <button type="button">Aceptar el inevitable</button>
            </div>
        `;

        overlay.querySelector('h2').textContent = state.winner.nick || 'Ganador';
        overlay.querySelector('p').textContent = state.winner.phrase || 'La ruleta ha hablado.';

        const debris = ['📻', '📋', '🫡', '⭐', '🎖️', '🗺️', '📡'];
        for (let index = 0; index < 34; index += 1) {
            const piece = document.createElement('span');
            piece.className = 'roulette-winner-celebration__debris';
            piece.textContent = debris[index % debris.length];
            piece.style.left = `${Math.random() * 100}%`;
            piece.style.animationDelay = `${Math.random() * 1.4}s`;
            piece.style.animationDuration = `${3.5 + Math.random() * 3}s`;
            piece.style.setProperty('--drift', `${-120 + Math.random() * 240}px`);
            piece.style.setProperty('--spin', `${300 + Math.random() * 850}deg`);
            overlay.appendChild(piece);
        }

        const close = () => overlay.remove();
        overlay.querySelector('button')?.addEventListener('click', close);
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                close();
            }
        });

        document.body.appendChild(overlay);
    };

    const applyState = (state, { animate = true } = {}) => {
        if (!state || typeof state !== 'object') {
            return;
        }

        const previousStatus = lastState?.status;
        updateServerClock(state);
        drawWheel(state.wheel);
        updateViewers(state);
        updateStatus(state);
        showResult(state);

        const spinIdentityChanged = !lastState
            || lastState.spin_started_at !== state.spin_started_at
            || lastState.spin_ends_at !== state.spin_ends_at
            || lastState.final_rotation !== state.final_rotation
            || previousStatus !== state.status;

        if (animate && spinIdentityChanged) {
            syncSpinAnimation(state);
        } else if (!animate) {
            if (state.status === 'completed') {
                setRotation(Number(state.final_rotation || 0));
            } else if (state.status === 'active') {
                setRotation(0);
            } else if (state.status === 'spinning') {
                syncSpinAnimation(state);
            }
        }

        if (state.status === 'completed') {
            launchWinnerCelebration(state);
        }

        lastState = state;

        if (['completed', 'closed', 'expired', 'failed'].includes(state.status) && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const fetchState = async () => {
        if (!stateUrl || requestBusy || document.visibilityState === 'hidden') {
            return;
        }

        requestBusy = true;
        try {
            const response = await fetch(stateUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const state = await response.json();
            applyState(state);
        } catch {
            // La siguiente pulsación vuelve a intentarlo sin sacar al usuario de la sala.
        } finally {
            requestBusy = false;
        }
    };

    const schedulePolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollTimer = window.setInterval(fetchState, 1000);
    };

    spinButton?.addEventListener('click', async () => {
        if (!spinUrl || spinBusy) {
            return;
        }

        spinBusy = true;
        spinButton.disabled = true;

        try {
            const response = await fetch(spinUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = payload?.message
                    || Object.values(payload?.errors || {})?.flat?.()?.[0]
                    || 'No se ha podido iniciar la ruleta.';
                await window.SqaDialog.alert(message);
                return;
            }

            if (payload.state) {
                applyState(payload.state);
            }
        } catch {
            await window.SqaDialog.alert('No se ha podido comunicar con la sala. Vuelve a intentarlo.');
        } finally {
            spinBusy = false;
            if (lastState) {
                updateStatus(lastState);
            }
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchState();
        }
    });

    wheelWrapper.addEventListener('pointermove', (event) => {
        const segment = findSegmentFromPointer(event);
        if (!segment) { hideTooltip(); return; }
        showTooltip(event, segment.label);
    });
    wheelWrapper.addEventListener('pointerleave', hideTooltip);
    window.addEventListener('scroll', hideTooltip, { passive: true });

    drawEmptyWheel();
    fetchState();
    schedulePolling();
});
