import React, { useState, useRef } from 'react';

/* Derive WordPress REST base from the GraphQL URL env var */
const WP_REST_BASE = import.meta.env.VITE_GRAPHQL_URL
  ? import.meta.env.VITE_GRAPHQL_URL.replace(/\/graphql$/, '') + '/wp-json'
  : '/wp-json';
const VERIFY_URL = `${WP_REST_BASE}/maljani/v1/verify`;

const STATUS_META = {
  active:    { label: 'ACTIVE',    color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
  approved:  { label: 'APPROVED',  color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
  confirmed: { label: 'CONFIRMED', color: '#60a5fa', bg: 'rgba(96,165,250,0.12)' },
};
const getStatusMeta = (s) =>
  STATUS_META[s] ?? { label: (s || 'UNKNOWN').toUpperCase(), color: '#94a3b8', bg: 'rgba(148,163,184,0.1)' };

const VerifyPolicy = ({ onNavigate }) => {
  const [mode, setMode]         = useState('traveler');
  const [form, setForm]         = useState({ policyNo: '', passport: '' });
  const [result, setResult]     = useState(null);
  const [loading, setLoading]   = useState(false);
  const [apiError, setApiError] = useState(null);
  const resultRef = useRef(null);

  const handleVerify = async (e) => {
    e.preventDefault();
    setLoading(true);
    setResult(null);
    setApiError(null);
    try {
      const qs = new URLSearchParams({
        policy_no: form.policyNo.trim().toUpperCase(),
        passport:  form.passport.trim().toUpperCase(),
      });
      const res = await fetch(`${VERIFY_URL}?${qs}`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!res.ok) throw new Error(`Server responded ${res.status}. Please try again.`);
      const data = await res.json();
      setResult(data);
      setTimeout(() => resultRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 120);
    } catch (err) {
      setApiError(err.message || 'Verification service temporarily unavailable.');
    } finally {
      setLoading(false);
    }
  };

  const handleReset = () => { setResult(null); setApiError(null); };
  const sm = result?.valid ? getStatusMeta(result.policyStatus) : null;

  return (
    <div style={{ paddingTop: 90, paddingBottom: 100, minHeight: '100vh' }}>
      <style>{`
        @keyframes vfade  { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:none; } }
        @keyframes vspin  { to { transform:rotate(360deg); } }
        .vf-in { animation: vfade 0.35s ease both; }
        .v-tab { padding:9px 20px; border-radius:10px; border:none; cursor:pointer;
          font-family:var(--font-body); font-size:13px; font-weight:600; transition:all 0.2s; }
        .v-tab.on  { background:var(--indigo); color:#fff; box-shadow:0 4px 12px rgba(79,70,229,0.35); }
        .v-tab.off { background:transparent; color:var(--slate); }
        .v-tab.off:hover { color:rgba(255,255,255,0.8); }
        .drow { display:flex; justify-content:space-between; align-items:baseline;
          padding:11px 0; border-bottom:1px solid rgba(255,255,255,0.06); }
        .drow:last-child { border-bottom:none; }
        .dk { font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:var(--slate); }
        .dv { font-size:14px; font-weight:700; color:#f1f5f9; text-align:right; max-width:60%; }
        .vf-input { width:100%; padding:13px 16px; background:rgba(255,255,255,0.05);
          border:1px solid var(--glass-border); border-radius:10px; color:#fff;
          font-family:monospace; font-size:15px; letter-spacing:0.04em;
          outline:none; transition:border-color 0.2s; box-sizing:border-box; }
        .vf-input:focus { border-color:var(--gold); }
        .vf-input::placeholder { color:var(--slate); font-family:var(--font-body); letter-spacing:0; }
      `}</style>

      <div className="container" style={{ maxWidth: 640 }}>

        {/* ── Page header ── */}
        <div style={{ textAlign: 'center', marginBottom: 44 }}>
          <p className="section-label">Anti-Fraud Portal</p>
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(30px,5vw,52px)', fontWeight: 800, lineHeight: 1.05, marginBottom: 16 }}>
            Verify a Certificate
          </h1>
          <p style={{ color: 'var(--slate)', fontSize: 16, lineHeight: 1.75, maxWidth: 480, margin: '0 auto 28px' }}>
            Every Maljani certificate carries a unique verification code.
            Confirm authenticity in under 3 seconds — no login required.
          </p>
          {/* Audience tabs */}
          <div style={{ display: 'inline-flex', background: 'rgba(0,0,0,0.3)', borderRadius: 14, padding: 4, gap: 4 }}>
            {[['traveler', '👤 Traveler / Individual'], ['embassy', '🏛️ Embassy / Official']].map(([m, lbl]) => (
              <button key={m} className={`v-tab ${mode === m ? 'on' : 'off'}`} onClick={() => { setMode(m); handleReset(); }}>{lbl}</button>
            ))}
          </div>
        </div>

        {/* ── Context hint ── */}
        <div style={{
          marginBottom: 24, padding: '12px 18px', borderRadius: 12,
          background: mode === 'embassy' ? 'rgba(49,99,49,0.12)' : 'rgba(55,65,81,0.25)',
          border: `1px solid ${mode === 'embassy' ? 'rgba(49,99,49,0.35)' : 'var(--glass-border)'}`,
          fontSize: 13, color: 'rgba(255,255,255,0.65)', display: 'flex', alignItems: 'flex-start', gap: 10,
        }}>
          <span style={{ fontSize: 18, flexShrink: 0 }}>{mode === 'embassy' ? '🏛️' : '🔍'}</span>
          {mode === 'embassy'
            ? "Embassy officers: enter the policy number printed on the applicant\u2019s certificate and their passport number as stated on the document."
            : 'Enter your policy number (from your confirmation email) and the passport or ID number used when purchasing.'}
        </div>

        {/* ── Main form card ── */}
        <div style={{
          background: 'var(--glass-bg-md, rgba(255,255,255,0.04))',
          border: '1px solid var(--glass-border-bright, rgba(255,255,255,0.12))',
          borderRadius: 'var(--radius-xl, 20px)', padding: '36px 40px',
          backdropFilter: 'blur(20px)', boxShadow: 'var(--shadow-card)',
        }}>
          <form onSubmit={handleVerify}>
            <div style={{ marginBottom: 20 }}>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--gold)', marginBottom: 8 }}>
                Policy Number
              </label>
              <input
                className="vf-input"
                type="text"
                placeholder="e.g. MAL-123456"
                value={form.policyNo}
                onChange={e => setForm(f => ({ ...f, policyNo: e.target.value }))}
                required autoComplete="off" spellCheck={false}
              />
            </div>
            <div style={{ marginBottom: 28 }}>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--gold)', marginBottom: 8 }}>
                {mode === 'embassy' ? "Applicant\u2019s Passport / ID Number" : 'Your Passport / National ID Number'}
              </label>
              <input
                className="vf-input"
                type="text"
                placeholder={mode === 'embassy' ? 'As stated on the certificate' : 'Used when you purchased the policy'}
                value={form.passport}
                onChange={e => setForm(f => ({ ...f, passport: e.target.value }))}
                required autoComplete="off" spellCheck={false}
              />
            </div>
            <button
              type="submit"
              className="btn btn--primary"
              style={{ width: '100%', justifyContent: 'center', fontSize: 15, fontWeight: 700, padding: '15px 20px', letterSpacing: '0.03em' }}
              disabled={loading || !form.policyNo.trim() || !form.passport.trim()}
            >
              {loading
                ? (
                  <span style={{ display: 'flex', alignItems: 'center', gap: 10, justifyContent: 'center' }}>
                    <span style={{ width: 16, height: 16, border: '2px solid rgba(255,255,255,0.25)', borderTopColor: '#fff', borderRadius: '50%', display: 'inline-block', animation: 'vspin 0.75s linear infinite' }} />
                    Checking records…
                  </span>
                ) : '🔍\u2002Verify Authenticity'}
            </button>
          </form>

          {apiError && (
            <div style={{ marginTop: 20, padding: '13px 16px', background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 10, fontSize: 13, color: '#fca5a5', display: 'flex', gap: 8 }}>
              <span>⚠️</span><span>{apiError}</span>
            </div>
          )}
        </div>

        {/* ── Result panel ── */}
        {result && (
          <div ref={resultRef} className="vf-in" style={{ marginTop: 28 }}>
            {result.valid ? (
              /* VALID */
              <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(34,197,94,0.3)', borderRadius: 'var(--radius-xl, 20px)', overflow: 'hidden' }}>
                <div style={{ background: 'linear-gradient(135deg,rgba(34,197,94,0.15),rgba(49,99,49,0.22))', padding: '24px 32px', borderBottom: '1px solid rgba(34,197,94,0.18)', display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                  <div style={{ width: 54, height: 54, borderRadius: '50%', background: 'rgba(34,197,94,0.15)', border: '2px solid rgba(34,197,94,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26, flexShrink: 0, color: '#22c55e' }}>✓</div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ color: '#86efac', fontSize: 11, fontWeight: 700, letterSpacing: '0.12em', textTransform: 'uppercase', marginBottom: 4 }}>Certificate Authenticated</div>
                    <div style={{ fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 800, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{result.policyTitle}</div>
                    <div style={{ fontSize: 13, color: 'var(--slate)', marginTop: 2 }}>Underwritten by {result.insurer}</div>
                  </div>
                  <div style={{ background: sm.bg, border: `1px solid ${sm.color}55`, borderRadius: 8, padding: '6px 14px', fontSize: 12, fontWeight: 800, color: sm.color, letterSpacing: '0.08em', flexShrink: 0 }}>
                    {sm.label}
                  </div>
                </div>
                <div style={{ padding: '24px 32px' }}>
                  {[
                    ['Policy Number',   `#${result.policyNumber}`],
                    ['Insured Name',    result.insuredName],
                    ['Passport / ID',   result.passportNumber],
                    ['Destination',     result.region || '— see certificate'],
                    ['Coverage Period', `${result.departure || '—'} → ${result.return || '—'}`],
                    ['Status',          sm.label],
                  ].map(([k, v]) => (
                    <div key={k} className="drow">
                      <span className="dk">{k}</span>
                      <span className="dv" style={k === 'Status' ? { color: sm.color } : {}}>{v}</span>
                    </div>
                  ))}
                  <div style={{ marginTop: 20, padding: '12px 16px', background: 'rgba(246,166,35,0.06)', border: '1px solid rgba(246,166,35,0.2)', borderRadius: 10, fontSize: 12, color: 'rgba(255,255,255,0.5)', display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                    <span style={{ color: 'var(--gold)', fontSize: 14, flexShrink: 0 }}>🔒</span>
                    <span>
                      Verified against Maljani records at <strong style={{ color: 'rgba(255,255,255,0.7)' }}>{new Date(result.verifiedAt).toUTCString()}</strong>. Ref: {result.policyNumber}.
                    </span>
                  </div>
                </div>
              </div>
            ) : (
              /* NOT FOUND */
              <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(239,68,68,0.25)', borderRadius: 'var(--radius-xl, 20px)', overflow: 'hidden' }}>
                <div style={{ background: 'rgba(239,68,68,0.07)', padding: '24px 32px', borderBottom: '1px solid rgba(239,68,68,0.15)', display: 'flex', alignItems: 'center', gap: 16 }}>
                  <div style={{ width: 54, height: 54, borderRadius: '50%', background: 'rgba(239,68,68,0.1)', border: '2px solid rgba(239,68,68,0.35)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26, flexShrink: 0, color: '#f87171' }}>✕</div>
                  <div>
                    <div style={{ color: '#f87171', fontSize: 11, fontWeight: 700, letterSpacing: '0.12em', textTransform: 'uppercase', marginBottom: 4 }}>Certificate Not Found</div>
                    <div style={{ fontSize: 15, fontWeight: 600 }}>{result.message}</div>
                  </div>
                </div>
                <div style={{ padding: '24px 32px' }}>
                  <p style={{ fontSize: 14, color: 'var(--slate)', lineHeight: 1.75, marginBottom: 16 }}>
                    Check for typos — policy numbers follow the format{' '}
                    <code style={{ background: 'rgba(255,255,255,0.07)', padding: '2px 7px', borderRadius: 5, fontFamily: 'monospace', fontSize: 13 }}>MAL-XXXXXX</code>.
                  </p>
                  <p style={{ fontSize: 13, color: 'var(--slate)', lineHeight: 1.7, marginBottom: 24 }}>
                    If a broker gave you a certificate that cannot be verified here, it may be{' '}
                    <strong style={{ color: '#f87171' }}>fraudulent</strong>. Report it to{' '}
                    <a href="mailto:verify@maljani.co.ke" style={{ color: '#f87171' }}>verify@maljani.co.ke</a>.
                  </p>
                  {onNavigate && (
                    <button className="btn btn--primary" onClick={() => onNavigate('landing')} style={{ width: '100%', justifyContent: 'center' }}>
                      Don't have insurance yet? Get covered in 4 minutes →
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>
        )}

        {/* ── Upsell strip ── */}
        <div style={{ marginTop: 44, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
          <div style={{ padding: '22px 24px', background: 'linear-gradient(135deg,rgba(49,99,49,0.14),rgba(34,197,94,0.05))', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 'var(--radius-lg, 14px)' }}>
            <div style={{ fontSize: 22, marginBottom: 8 }}>✈️</div>
            <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 6 }}>Traveling soon?</div>
            <p style={{ fontSize: 13, color: 'var(--slate)', lineHeight: 1.6, marginBottom: 14 }}>Get embassy-verified travel insurance in under 4 minutes.</p>
            {onNavigate && <button className="btn btn--ghost btn--sm" onClick={() => onNavigate('landing')}>Compare Policies →</button>}
          </div>
          <div style={{ padding: '22px 24px', background: 'rgba(55,65,81,0.25)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg, 14px)' }}>
            <div style={{ fontSize: 22, marginBottom: 8 }}>🏢</div>
            <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 6 }}>Travel agency?</div>
            <p style={{ fontSize: 13, color: 'var(--slate)', lineHeight: 1.6, marginBottom: 14 }}>Issue and track verified certificates in bulk for your clients.</p>
            {onNavigate && <button className="btn btn--ghost btn--sm" onClick={() => onNavigate('agencies')}>Agency Dashboard →</button>}
          </div>
        </div>

      </div>
    </div>
  );
};

export default VerifyPolicy;
