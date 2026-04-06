import React from 'react';
import { useResponsive } from '../lib/useResponsive';

const STATS = [
  { value: '50,000+', label: 'Policies Issued'   },
  { value: '40+',     label: 'Countries Covered' },
  { value: '200+',    label: 'Agency Partners'   },
  { value: '15+',     label: 'Insurer Partners'  },
];

const CHIPS = ['🏛️ Embassy-accepted', '⚡ 4-min issuance', '🔒 Fraud-proof'];

const TrustSection = ({ onNavigate }) => {
  const { mobile } = useResponsive();
  return (
  <section style={{ position: 'relative', zIndex: 1 }}>

    {/* Stats bar */}
    <div style={{
      borderTop: '1px solid var(--glass-border)',
      borderBottom: '1px solid var(--glass-border)',
      background: 'rgba(8,14,39,0.65)',
      backdropFilter: 'blur(12px)',
    }}>
      <div className="container">
        <div style={{ display: 'grid', gridTemplateColumns: mobile ? 'repeat(2,1fr)' : 'repeat(4,1fr)' }}>
          {STATS.map((s, i) => (
            <div
              key={s.label}
              className="reveal"
              style={{
                padding: mobile ? '18px 12px' : '26px 24px',
                textAlign: 'center',
                borderRight: mobile ? (i % 2 === 0 ? '1px solid var(--glass-border)' : 'none') : (i < STATS.length - 1 ? '1px solid var(--glass-border)' : 'none'),
                borderBottom: mobile && i < 2 ? '1px solid var(--glass-border)' : 'none',
              }}
            >
              <div style={{
                fontFamily: 'var(--font-display)', fontWeight: 800,
                fontSize: 'clamp(24px,2.8vw,36px)', lineHeight: 1.1, marginBottom: 5,
                background: 'linear-gradient(135deg,#fff 30%,var(--gold))',
                WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent',
                backgroundClip: 'text',
              }}>
                {s.value}
              </div>
              <div style={{ fontSize: 12, color: 'var(--slate)', letterSpacing: '0.06em', textTransform: 'uppercase', fontWeight: 600 }}>
                {s.label}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>

    {/* Anti-fraud banner */}
    <div style={{
      background: 'linear-gradient(135deg,rgba(49,99,49,0.12),rgba(246,166,35,0.05))',
      borderBottom: '1px solid rgba(49,99,49,0.18)',
    }}>
      <div className="container">
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          flexWrap: 'wrap', gap: 20, padding: '20px 0',
        }}>

          {/* Left: shield + message */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
            <div style={{
              width: 42, height: 42, borderRadius: '50%', flexShrink: 0,
              background: 'rgba(34,197,94,0.1)', border: '1.5px solid rgba(34,197,94,0.35)',
              display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 20,
            }}>
              🛡️
            </div>
            <div>
              <div style={{ fontSize: 13, fontWeight: 700, color: '#86efac', marginBottom: 2 }}>
                Cryptographically Verified Certificates
              </div>
              <p style={{ fontSize: 13, color: 'var(--slate)', margin: 0, lineHeight: 1.5 }}>
                Every Maljani certificate has a unique code embassies and hotels can check in 3 seconds.
              </p>
            </div>
          </div>

          {/* Right: chips + CTA */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
            {CHIPS.map(chip => (
              <span key={chip} style={{
                fontSize: 12, fontWeight: 600, color: 'rgba(255,255,255,0.6)',
                background: 'rgba(255,255,255,0.04)', border: '1px solid var(--glass-border)',
                borderRadius: 999, padding: '5px 12px', whiteSpace: 'nowrap',
              }}>
                {chip}
              </span>
            ))}
            <button
              onClick={() => onNavigate ? onNavigate('verify') : undefined}
              style={{
                fontSize: 13, fontWeight: 700, color: 'var(--gold)',
                background: 'none', border: 'none', cursor: 'pointer',
                padding: 0, textDecoration: 'underline', textUnderlineOffset: 3,
                whiteSpace: 'nowrap',
              }}
            >
              Verify a certificate →
            </button>
          </div>

        </div>
      </div>
    </div>

  </section>
  );
};

export default TrustSection;
