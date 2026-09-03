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
    let lastState = null;
    let serverOffsetMs = 0;
    let frame = null;
    let pollTimer = null;
    let requestBusy = false;
    let spinBusy = false;
    let currentRotation = 0;
    let lastWheelSignature = '';

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

    const drawWheel = (wheel) => {
        const candidates = Array.isArray(wheel) ? wheel.filter((candidate) => Number(candidate.tickets) > 0) : [];
        const signature = JSON.stringify(candidates.map((candidate) => [candidate.user_id, candidate.nick, candidate.tickets]));

        if (signature === lastWheelSignature) {
            return;
        }
        lastWheelSignature = signature;

        if (candidates.length === 0) {
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

        candidates.forEach((candidate, index) => {
            const fraction = Number(candidate.tickets) / totalTickets;
            const arc = fraction * Math.PI * 2;
            const end = cursor + arc;

            context.beginPath();
            context.moveTo(center, center);
            context.arc(center, center, outerRadius, cursor, end);
            context.closePath();
            context.fillStyle = colorForIndex(index, candidates.length);
            context.fill();
            context.lineWidth = 3;
            context.strokeStyle = '#0b1017';
            context.stroke();

            const midpoint = cursor + (arc / 2);
            const x = center + Math.cos(midpoint) * textRadius;
            const y = center + Math.sin(midpoint) * textRadius;
            const label = String(candidate.nick || 'Jugador');
            const tickets = Number(candidate.tickets || 0);

            context.save();
            context.translate(x, y);
            let textRotation = midpoint + Math.PI / 2;
            if (midpoint > Math.PI / 2 && midpoint < (Math.PI * 3) / 2) {
                textRotation += Math.PI;
            }
            context.rotate(textRotation);
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.shadowColor = 'rgba(0, 0, 0, .5)';
            context.shadowBlur = 4;
            context.fillStyle = '#fff';
            const fontSize = Math.max(13, Math.min(22, 17 - Math.max(0, candidates.length - 10) * .28));
            context.font = `800 ${fontSize}px system-ui, sans-serif`;
            context.fillText(label.length > 15 ? `${label.slice(0, 14)}…` : label, 0, -8);
            context.fillStyle = 'rgba(255,255,255,.78)';
            context.font = `700 ${Math.max(11, fontSize - 4)}px system-ui, sans-serif`;
            context.fillText(`${tickets} ${tickets === 1 ? 'papeleta' : 'papeletas'}`, 0, 12);
            context.restore();

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
                window.alert(message);
                return;
            }

            if (payload.state) {
                applyState(payload.state);
            }
        } catch {
            window.alert('No se ha podido comunicar con la sala. Vuelve a intentarlo.');
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

    drawEmptyWheel();
    fetchState();
    schedulePolling();
});
