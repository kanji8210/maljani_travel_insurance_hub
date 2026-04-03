import React from 'react';

const PARTNERS = ['Jubilee Insurance','AAR Insurance','Britam Group','CIC Group','UAP Old Mutual','Kenya Orient','Resolution Insurance','Heritage Insurance'];

const TrustedPartners = () => (
  <section style={{ position: 'relative', zIndex: 1, padding: '0 0 90px' }}>
    <div className="container">
      <p style={{ textAlign: 'center', fontSize: 12, fontWeight: 700, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--slate-dark)', marginBottom: 28 }}>Trusted by leading African insurers</p>
      <div style={{ overflow: 'hidden', maskImage: 'linear-gradient(90deg,transparent,black 8%,black 92%,transparent)' }} aria-hidden="true">
        <div style={{ display: 'flex', alignItems: 'center', gap: 40, animation: 'scroll-lr 28s linear infinite', width: 'max-content' }}>
          <style>{`@keyframes scroll-lr { from{transform:translateX(0)} to{transform:translateX(-50%)} }`}</style>
          {[...PARTNERS, ...PARTNERS].map((p, i) => (
            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '11px 22px', borderRadius: 100, background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', fontSize: 13, fontWeight: 600, color: 'rgba(255,255,255,0.45)', whiteSpace: 'nowrap', flexShrink: 0 }}>
              <div style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--indigo)', flexShrink: 0 }} />
              {p}
            </div>
          ))}
        </div>
      </div>
    </div>
  </section>
);

export default TrustedPartners;
