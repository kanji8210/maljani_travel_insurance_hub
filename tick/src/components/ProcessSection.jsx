import React from 'react';
import { useResponsive } from '../lib/useResponsive';

const STEPS = [
  { n: 1, emoji: '🔍', title: 'Compare Policies',       desc: 'Enter your trip details and compare from 15+ insurers — sorted by price, coverage quality, and verified reviews.' },
  { n: 2, emoji: '💳', title: 'Pay Securely',           desc: 'Checkout via Pesapal with M-Pesa, Visa, or bank transfer. Every transaction is encrypted end-to-end.' },
  { n: 3, emoji: '🔄', title: 'Download Certificate',  desc: 'Receive your PDF travel certificate and QR-verified embassy letter instantly upon payment confirmation.' },
];

const ProcessSection = () => {
  const { mobile } = useResponsive();
  return (
  <section style={{ position: 'relative', zIndex: 1, padding: mobile ? '60px 0' : '110px 0' }}>
    <div className="container">
      <div className="section-header">
        <p className="section-label">3 Simple Steps</p>
        <h2 className="section-title reveal" style={{ fontSize: 'clamp(30px,3.5vw,50px)', marginBottom: 16 }}>Covered in Minutes</h2>
        <p className="reveal reveal-delay-1">From comparison to issued certificate — no broker visits, no waiting rooms.</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : 'repeat(3,1fr)', gap: 28, position: 'relative' }}>
        {/* Dashed connector */}
        {!mobile && <div style={{ position: 'absolute', top: 44, left: 'calc(16.67% + 20px)', right: 'calc(16.67% + 20px)', height: 1, background: 'repeating-linear-gradient(90deg,var(--indigo) 0,var(--indigo) 6px,transparent 6px,transparent 14px)', opacity: 0.35, pointerEvents: 'none' }} aria-hidden="true" />}

        {STEPS.map((s, i) => (
          <div key={s.n} className={`reveal reveal-delay-${i}`} style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '40px 32px', textAlign: 'center', backdropFilter: 'blur(10px)', transition: 'border-color 0.3s, transform 0.3s, box-shadow 0.3s' }}
            onMouseEnter={e => { e.currentTarget.style.borderColor = 'rgba(49,99,49,0.4)'; e.currentTarget.style.transform = 'translateY(-6px)'; }}
            onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--glass-border)'; e.currentTarget.style.transform = 'none'; }}
          >
            <div style={{ width: 52, height: 52, borderRadius: '50%', background: 'linear-gradient(135deg,var(--indigo),var(--indigo-glow))', display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 800, margin: '0 auto 20px', boxShadow: '0 0 24px rgba(49,99,49,0.45)' }} aria-hidden="true">{s.n}</div>
            <div style={{ fontSize: 34, marginBottom: 16 }} aria-hidden="true">{s.emoji}</div>
            <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 21, fontWeight: 700, marginBottom: 12 }}>{s.title}</h3>
            <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75 }}>{s.desc}</p>
          </div>
        ))}
      </div>
    </div>
  </section>
  );
};

export default ProcessSection;
