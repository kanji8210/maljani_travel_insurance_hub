import React from 'react';
import { useResponsive } from '../lib/useResponsive';

const Footer = ({ onNavigate }) => {
  const { mobile, tablet } = useResponsive();
  const cols = [
    {
      title: 'Products',
      links: [
        { label: 'Travel Insurance',   view: 'catalog' },
        { label: 'Group Policies',     view: 'catalog' },
        { label: 'Annual Multi-Trip',  view: 'catalog' },
        { label: 'Embassy Letters',    view: 'catalog' },
      ],
    },
    {
      title: 'For Agencies',
      links: [
        { label: 'Partner Portal',       view: 'login' },
        { label: 'Commission Structure', view: 'agencies' },
        { label: 'Onboarding Guide',     view: 'agencies' },
        { label: 'API Access',           view: 'about' },
      ],
    },
    {
      title: 'Support',
      links: [
        { label: 'Verify a Policy', view: 'verify' },
        { label: 'File a Claim',    view: 'catalog' },
        { label: 'Contact Us',      view: 'about' },
        { label: 'FAQ',             view: 'about' },
      ],
    },
  ];

  return (
    <footer style={{ position: 'relative', zIndex: 1, borderTop: '1px solid var(--glass-border)', padding: '64px 0 36px' }}>
      <div className="container">

        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : tablet ? '1fr 1fr' : '2fr 1fr 1fr 1fr', gap: mobile ? 32 : tablet ? 36 : 56, marginBottom: 48 }}>

          {/* Brand */}
          <div>
            <button onClick={() => onNavigate('landing')} style={{ display: 'flex', alignItems: 'center', gap: 10, background: 'none', border: 'none', cursor: 'pointer', padding: 0, marginBottom: 16 }}>
              <img
                src="https://mtj.ivk.mybluehost.me/website_e48ea083/wp-content/uploads/2026/03/logo-type.png"
                alt="TIC-Kenya"
                style={{ width: 36, height: 36, objectFit: 'contain', borderRadius: 9, flexShrink: 0 }}
              />
              <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 19, color: '#fff' }}>TIC<span style={{ color: 'var(--gold)' }}>-Kenya</span></span>
            </button>
            <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75, maxWidth: 270, marginBottom: 20 }}>
              Kenya's trusted travel insurance aggregator. Compare, buy, and travel protected.
            </p>
            <div style={{ display: 'flex', gap: 9 }}>
              {['𝕏', 'in', 'f', '◻'].map((s, i) => (
                <a key={i} href="#" style={{ width: 35, height: 35, borderRadius: 8, background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, color: 'var(--slate)', textDecoration: 'none', transition: 'all 0.2s' }} aria-label="Social">{s}</a>
              ))}
            </div>
          </div>

          {/* Link columns */}
          {cols.map((col) => (
            <div key={col.title}>
              <h4 style={{ fontFamily: 'var(--font-display)', fontSize: 13, fontWeight: 700, marginBottom: 18, color: 'rgba(255,255,255,0.9)', letterSpacing: '0.04em' }}>{col.title}</h4>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 11 }}>
                {col.links.map((l) => (
                  <li key={l.label}>
                    <button className="footer-link" onClick={() => onNavigate(l.view)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--slate)', fontSize: 14, padding: 0, fontFamily: 'var(--font-body)' }}>
                      {l.label}
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Bottom bar */}
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', paddingTop: 28, borderTop: '1px solid var(--glass-border)', flexWrap: 'wrap', gap: 14 }}>
          <p style={{ fontSize: 13, color: 'var(--slate-dark)' }}>© 2026 Travel Insurance Center-Kenya. All rights reserved.</p>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
            {['✓ IRA Compliant', '✓ SSL Secured', '✓ GDPR Ready'].map((b) => (
              <span key={b} style={{ display: 'flex', alignItems: 'center', gap: 5, padding: '5px 11px', background: 'rgba(34,197,94,0.10)', border: '1px solid rgba(34,197,94,0.25)', borderRadius: 100, fontSize: 11, fontWeight: 600, color: '#6ee7b7' }}>{b}</span>
            ))}
          </div>
        </div>

      </div>
    </footer>
  );
};

export default Footer;
