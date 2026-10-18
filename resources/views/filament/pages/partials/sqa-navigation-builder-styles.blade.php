<style>
  /* Standalone styling for custom Filament Alpine editors; no Tailwind compilation required. */
  .sqa-builder{color:var(--text-primary,#f4f4f5);font-size:14px}
  .sqa-builder *{box-sizing:border-box}
  .sqa-builder__layout{display:grid;grid-template-columns:minmax(265px,340px) minmax(0,1fr);gap:20px;align-items:start}
  .sqa-builder__panel{min-width:0;padding:18px;background:var(--gray-50,#1a1b20);background:light-dark(#f9fafb,#191b20);border:1px solid light-dark(#e5e7eb,#3b3d44);border-radius:13px;display:grid;gap:14px}
  .dark .sqa-builder__panel{background:#191b20;border-color:#393b43}
  .sqa-builder__title{font-size:16px;font-weight:700;margin:0}
  .sqa-builder__help{color:light-dark(#5e6675,#9ca3af);font-size:12px;line-height:1.5;margin:0}
  .sqa-builder__label{display:grid;gap:5px;font-size:13px;font-weight:600}
  .sqa-builder input:not([type=checkbox]),.sqa-builder select{display:block;width:100%;background:light-dark(#fff,#24262d);color:inherit;border:1px solid light-dark(#d1d5db,#4b4c54);border-radius:8px;padding:10px 11px;min-height:40px;outline:none}
  .sqa-builder input:focus-visible,.sqa-builder select:focus-visible{outline:2px solid #e99b10;outline-offset:1px}
  .dark .sqa-builder input:not([type=checkbox]),.dark .sqa-builder select{background:#24262d;border-color:#4b4c54;color:#f4f4f5}
  .sqa-builder input[type=checkbox]{accent-color:#eaa00b;width:15px;height:15px}
  .sqa-builder__stack{display:grid;gap:10px;min-width:0}
  .sqa-builder__catalog{display:grid;gap:9px;max-height:405px;overflow:auto;padding-right:5px}
  .sqa-builder__entry,.sqa-builder__card{display:flex;gap:12px;align-items:center;padding:11px;background:light-dark(#fff,#202127);border:1px solid light-dark(#e5e7eb,#383b43);border-radius:10px;min-width:0}
  .dark .sqa-builder__entry,.dark .sqa-builder__card{background:#202127;border-color:#383b43}
  .sqa-builder__entry{cursor:grab;justify-content:space-between}
  .sqa-builder__entry-text{min-width:0;overflow:hidden;flex:1 1 auto}
  .sqa-builder__entry-text strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px}
  .sqa-builder__entry-text small{display:block;font-size:11px;color:light-dark(#71717a,#a1a1aa);margin-top:3px}
  .sqa-builder__card{display:block;padding:15px}
  .sqa-builder__card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
  .sqa-builder__card-title{flex:1 1 auto;min-width:0;display:grid;gap:8px}
  .sqa-builder__kind{text-transform:uppercase;font-weight:700;letter-spacing:.06em;font-size:10px;color:#e9a329}
  .sqa-builder__actions{display:flex;gap:7px;flex-wrap:wrap}
  .sqa-builder__button{padding:8px 11px;display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:8px;background:light-dark(#fff,#292b32);border:1px solid light-dark(#d1d5db,#53545d);font-size:12px;font-weight:650;white-space:nowrap;cursor:pointer;min-height:36px;color:inherit}
  .sqa-builder__button:hover{border-color:#eda220;background:light-dark(#fff8e9,#3b3123)}
  .sqa-builder__button--accent{border-color:#b97806;background:#f59e0b;color:#111827}
  .sqa-builder__button--accent:hover{background:#fbbf24;color:#111827}
  .sqa-builder__button--danger{color:#ef7272}
  .sqa-builder__button:disabled{opacity:.35;cursor:not-allowed}
  .sqa-builder__split{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
  .sqa-builder__selection{display:grid;gap:8px;padding:14px;border:1px dashed light-dark(#d4d4d8,#52525b);border-radius:12px;min-height:90px;align-content:start}
  .sqa-builder__details{margin-top:12px;border-top:1px solid light-dark(#d4d4d8,#3f414a);padding-top:12px}
  .sqa-builder__details summary{cursor:pointer;color:#e9a329;font-weight:650}
  .sqa-builder__checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-top:10px}
  .sqa-builder__checks label{display:flex;gap:7px;align-items:center;font-size:11px;min-width:0}
  .sqa-builder__divider{border-top:1px solid light-dark(#e4e4e7,#42444a);padding-top:14px}
  .sqa-builder__message{font-size:13px;font-weight:600}
  .sqa-builder__message--error{color:#fb7185}.sqa-builder__message--ok{color:#4ade80}
  .sqa-builder__icon{width:22px;height:22px;stroke:currentColor;fill:none;flex:none}
  .sqa-builder__icon-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(40px,1fr));gap:6px;max-height:240px;overflow:auto;padding:5px;border:1px solid light-dark(#e5e7eb,#40424c);border-radius:9px}
  .sqa-builder__icon-choice{height:39px;display:grid;place-items:center;border:1px solid light-dark(#e5e7eb,#50505a);background:light-dark(#fff,#24262d);border-radius:7px;cursor:pointer}
  .sqa-builder__icon-choice[aria-pressed=true]{border-color:#f59e0b;background:#46351c;color:#ffc360}
  .sqa-builder__icon-choice:hover{border-color:#e9a329}
  .sqa-builder__options{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
  .sqa-builder__options label{display:flex;gap:7px;align-items:center}
  .sqa-builder__preview{display:flex;align-items:center;gap:8px;color:#f0a21b;font-weight:650}
  [x-cloak]{display:none!important}
  @media(max-width:960px){.sqa-builder__layout{grid-template-columns:1fr}.sqa-builder__catalog{max-height:280px}}
  @media(max-width:640px){.sqa-builder__panel{padding:12px}.sqa-builder__card-head{flex-direction:column}.sqa-builder__actions{justify-content:flex-start}}
</style>
