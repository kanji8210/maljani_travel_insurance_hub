import React from 'react';
import { useResponsive } from '../lib/useResponsive';

const VALUES = [
  { icon: '�️', title: 'Cryptographic Authentication', desc: 'Every certificate we issue contains a unique hash code. Embassies, hotels, and employers can verify it in 3 seconds at verify.maljani.co.ke.' },
  { icon: '⚡', title: '4-Minute Issuance', desc: 'From comparison to signed PDF certificate in under 4 minutes. No broker visits, no waiting rooms, no fax machines.' },
  { icon: '🌍', title: 'Africa-Native Design', desc: 'Built for KES, TZS, UGX and East African travel corridors — not retrofitted from a Western product.' },
  { icon: '🏛️', title: 'Embassy-Trusted', desc: 'All certificates comply with FRA standards and are accepted at Schengen embassies, hotels, and border control across 40+ countries.' },
];

const MILESTONES = [
  { year: '2021', title: 'Founded in Nairobi', desc: 'Maljani started with a single question: why does it take 3 days to get travel insurance in East Africa?' },
  { year: '2022', title: 'First Insurer Partnerships', desc: 'Signed agreements with 5 leading insurers. Built the first version of the certificate engine.' },
  { year: '2023', title: 'Agency Platform Launch', desc: 'Launched the agency dashboard. 50 agencies onboarded in the first 90 days.' },
  { year: '2024', title: '100,000 Policies Milestone', desc: 'Crossed 100,000 policies issued. KES 1B+ in premiums processed. Expanded to Uganda and Tanzania.' },
  { year: '2025', title: '200+ Agency Partners', desc: 'Platform now covers Schengen, Worldwide, and all major East Africa corridors with 15+ insurer partners.' },
  { year: '2026', title: 'The Next Chapter', desc: 'Launching API access, group policy tools, and the Maljani mobile app for agents on the go.' },
];

const TEAM = [
  { name: 'David K.', role: 'Co-founder & CEO', emoji: '👨🏿', note: 'Former underwriter at Jubilee Insurance. Obsessed with distribution.' },
  { name: 'Wanjiku M.', role: 'Co-founder & CTO', emoji: '👩🏾‍💻', note: 'Built fintech infrastructure for 3 East African banks before Maljani.' },
  { name: 'Omar A.', role: 'Head of Partnerships', emoji: '👨🏽‍💼', note: 'Grew up in a travel agency family in Mombasa. Knows every pain point.' },
  { name: 'Faith N.', role: 'Head of Operations', emoji: '👩🏾‍⚕️', note: 'Insurance actuary. Makes sure claims actually get paid.' },
];

const AboutPage = ({ onNavigate }) => {
  const { mobile, tablet } = useResponsive();
  return (
    <div style={{ paddingTop: 90 }}>
      <style>{`
        @keyframes about-ring { to { transform: translateY(-50%) rotate(360deg); } }
        .timeline-dot { width: 12px; height: 12px; background: var(--gold); border-radius: 50%; flex-shrink: 0; margin-top: 5px; box-shadow: 0 0 12px rgba(246,166,35,0.5); }
        .value-card:hover { border-color: rgba(49,99,49,0.5) !important; transform: translateY(-3px); }
        .value-card { transition: all 0.25s ease; }
      `}</style>

      {/* ── Hero ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '96px 0 96px', textAlign: 'center', overflow: 'hidden' }}>
        <div style={{
          position: 'absolute', left: '50%', top: '50%', transform: 'translate(-50%,-50%)',
          width: mobile ? 300 : tablet ? 480 : 640, height: mobile ? 300 : tablet ? 480 : 640, border: '1px dashed rgba(246,166,35,0.12)', borderRadius: '50%',
          pointerEvents: 'none', animation: 'about-ring 100s linear infinite',
        }} aria-hidden="true" />
        <div style={{
          position: 'absolute', left: '50%', top: '50%', transform: 'translate(-50%,-50%)',
          width: mobile ? 180 : tablet ? 280 : 380, height: mobile ? 180 : tablet ? 280 : 380, border: '1px dashed rgba(49,99,49,0.15)', borderRadius: '50%',
          pointerEvents: 'none', animation: 'about-ring 60s linear infinite reverse',
        }} aria-hidden="true" />

        <div className="container" style={{ position: 'relative', zIndex: 1 }}>
          <p className="section-label">About Maljani</p>
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(40px,5vw,72px)', fontWeight: 800, lineHeight: 1.05, letterSpacing: '-0.025em', marginBottom: 24, maxWidth: 820, margin: '0 auto 24px' }}>
            The Anti-Fraud{' '}
            <em style={{ fontStyle: 'normal', background: 'linear-gradient(135deg,var(--indigo-glow),#86efac)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', backgroundClip: 'text' }}>Travel Insurance</em>
            {' '}Platform.
          </h1>
          <p style={{ color: 'var(--slate)', fontSize: 18, lineHeight: 1.8, maxWidth: 640, margin: '0 auto 44px' }}>
            Fake insurance certificates cost travelers visa rejections and leave them unprotected abroad. Maljani exists to end that — with instant, cryptographically verified certificates that any embassy can check in 3 seconds.
          </p>
          <div style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap' }}>
            <button className="btn btn--primary btn--lg" onClick={() => onNavigate('catalog')}>Compare Policies &rarr;</button>
            <button className="btn btn--ghost btn--lg" onClick={() => onNavigate('agencies')}>For Agencies</button>
          </div>
        </div>
      </section>

      {/* ── Stats ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? 'repeat(2,1fr)' : 'repeat(4,1fr)', gap: 1, background: 'var(--glass-border)', borderRadius: 'var(--radius-lg)', overflow: 'hidden', border: '1px solid var(--glass-border)' }}>
            {[['50,000+','Travelers Insured'],['200+','Agency Partners'],['15+','Insurer Partners'],['4 min','Avg. Certificate Time']].map(([n,l]) => (
              <div key={l} style={{ background: 'var(--navy)', padding: '36px 28px', textAlign: 'center' }}>
                <div style={{ fontFamily: 'var(--font-display)', fontSize: 40, fontWeight: 800, color: 'var(--gold)', lineHeight: 1 }}>{n}</div>
                <div style={{ fontSize: 13, color: 'var(--slate)', marginTop: 10 }}>{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Mission ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: mobile ? 40 : 80, alignItems: 'center' }}>
            <div>
              <p className="section-label">Our Mission</p>
              <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(28px,3vw,44px)', fontWeight: 800, lineHeight: 1.1, marginBottom: 22 }}>
                Fake Certificates Are a{' '}
                <em style={{ fontStyle: 'normal', color: 'var(--gold)' }}>Real Problem.</em>
              </h2>
              <p style={{ color: 'var(--slate)', fontSize: 15, lineHeight: 1.85, marginBottom: 18 }}>
                Thousands of travelers in East Africa unknowingly carry photoshopped or expired insurance certificates. Embassies can’t easily verify them. Hotels accept them on faith. Border control lacks tools to check. Travelers end up stranded, unprotected, and with rejected visas.
              </p>
              <p style={{ color: 'var(--slate)', fontSize: 15, lineHeight: 1.85, marginBottom: 18 }}>
                Maljani solves this at the root. Every certificate we issue contains a unique cryptographic code tied to our records. Any embassy officer can enter that code at verify.maljani.co.ke and confirm authenticity in under 3 seconds — no login, no account, no phone calls.
              </p>
              <p style={{ color: 'var(--slate)', fontSize: 15, lineHeight: 1.85 }}>
                We work directly with FRA-licensed insurers in Kenya, Uganda, and Tanzania. Every certificate is legally valid for Schengen, worldwide, and all East Africa corridor visa applications.
              </p>
            </div>
            <div>
              {/* visual: mission pillars */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {[['Cryptographic Verification', 'Every certificate has a unique code tied to our records. Enter it at verify.maljani.co.ke — authentic or flagged in 3 seconds.', '#86efac'],
                  ['4-Minute Issuance', 'Compare, pay, download — faster than it takes to brew tea. Certificate arrives in your inbox instantly.', 'var(--gold)'],
                  ['Zero Hidden Clauses', 'Every exclusion, sublimit, and term is shown before you buy. No asterisks pointing to 48-page appendices.', '#6ee7b7'],
                  ['Embassy-Accepted', 'All policies are FRA-compliant and accepted at Schengen embassies, hotels, and border crossings in 40+ countries.', '#a78bfa']
                ].map(([title, desc, color]) => (
                  <div key={title} style={{ display: 'flex', gap: 16, padding: '18px 20px', background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-md)' }}>
                    <div style={{ width: 4, background: color, borderRadius: 2, flexShrink: 0 }} />
                    <div>
                      <div style={{ fontWeight: 700, fontSize: 15, marginBottom: 4, color: color }}>{title}</div>
                      <div style={{ fontSize: 13, color: 'var(--slate)', lineHeight: 1.7 }}>{desc}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── How Verification Works ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">Anti-Fraud System</p>
            <h2 className="section-title">How Certificate Verification Works</h2>
            <p style={{ color: 'var(--slate)', maxWidth: 540, margin: '0 auto' }}>Four steps that make fake insurance impossible to pass off as authentic.</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : tablet ? 'repeat(2,1fr)' : 'repeat(4,1fr)', gap: 20, marginTop: 52, position: 'relative' }}>
            {!mobile && <div style={{ position: 'absolute', top: 36, left: 'calc(12.5% + 10px)', right: 'calc(12.5% + 10px)', height: 1, background: 'repeating-linear-gradient(90deg,var(--gold) 0,var(--gold) 6px,transparent 6px,transparent 14px)', opacity: 0.3, pointerEvents: 'none' }} />}
            {[
              { n: '01', icon: '💳', title: 'Buy Policy',        desc: 'Purchase from any of our 15+ licensed insurers in under 4 minutes.' },
              { n: '02', icon: '📄', title: 'Receive Certificate', desc: 'Your PDF certificate arrives instantly with a unique verification code embedded.' },
              { n: '03', icon: '🏛️', title: 'Share with Embassy', desc: 'Embassy officer visits verify.maljani.co.ke and enters your policy number + passport.' },
              { n: '04', icon: '✅', title: 'Verified in 3 Seconds', desc: 'Our system returns: insured name, coverage dates, region, and active status. Instantly.' },
            ].map((step, i) => (
              <div key={step.n} style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '32px 24px', textAlign: 'center', position: 'relative' }}>
                <div style={{ position: 'absolute', top: -18, left: '50%', transform: 'translateX(-50%)', background: 'linear-gradient(135deg,var(--indigo),var(--indigo-glow))', color: '#fff', fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 13, padding: '4px 14px', borderRadius: 99, letterSpacing: '0.05em' }}>{step.n}</div>
                <div style={{ fontSize: 36, margin: '10px 0 14px' }}>{step.icon}</div>
                <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 15, fontWeight: 700, marginBottom: 10 }}>{step.title}</h3>
                <p style={{ color: 'var(--slate)', fontSize: 13, lineHeight: 1.75 }}>{step.desc}</p>
              </div>
            ))}
          </div>
          <div style={{ textAlign: 'center', marginTop: 36 }}>
            <button className="btn btn--ghost" onClick={() => onNavigate('verify')}>Try the verification portal →</button>
          </div>
        </div>
      </section>

      {/* ── For Tourists ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div style={{ background: 'linear-gradient(135deg,rgba(246,166,35,0.08),rgba(49,99,49,0.1))', border: '1px solid rgba(246,166,35,0.2)', borderRadius: 'var(--radius-xl)', padding: mobile ? '36px 24px' : '60px 64px', display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr auto', gap: mobile ? 28 : 48, alignItems: 'center' }}>
            <div>
              <p className="section-label" style={{ textAlign: 'left' }}>Visiting East Africa?</p>
              <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(26px,3vw,40px)', fontWeight: 800, lineHeight: 1.1, marginBottom: 18 }}>
                Your Visa May Require Travel Insurance.
              </h2>
              <p style={{ color: 'var(--slate)', fontSize: 15, lineHeight: 1.85, marginBottom: 18 }}>
                Kenya, Tanzania, and Uganda require proof of travel insurance for visa on arrival and e-visa applications. Maljani offers single-trip and multi-entry policies starting from KES 800 — with your certificate ready before your boarding call.
              </p>
              <p style={{ color: 'var(--slate)', fontSize: 15, lineHeight: 1.85, marginBottom: 28 }}>
                All certificates are issued in English, accepted at all East African embassies and border points, and verifiable online. No local agent needed.
              </p>
              <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                <button className="btn btn--gold btn--lg" onClick={() => onNavigate('catalog')}>Get Insurance for Your Trip →</button>
                <button className="btn btn--ghost" onClick={() => onNavigate('verify')}>Verify an Existing Certificate</button>
              </div>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12, minWidth: 200 }}>
              {['✅ Kenya e-Visa accepted','✅ Tanzania endorsed','✅ Uganda border-ready','✅ Schengen compliant','✅ Instant PDF delivery'].map(item => (
                <div key={item} style={{ fontSize: 14, fontWeight: 600, color: 'rgba(255,255,255,0.8)', display: 'flex', alignItems: 'center', gap: 8 }}>{item}</div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ── Values ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">What We Stand For</p>
            <h2 className="section-title">Our Values</h2>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? 'repeat(2,1fr)' : 'repeat(4,1fr)', gap: 20, marginTop: 48 }}>
            {VALUES.map(v => (
              <div key={v.title} className="value-card" style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '28px 22px', textAlign: 'center' }}>
                <div style={{ fontSize: 36, marginBottom: 16 }}>{v.icon}</div>
                <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 16, fontWeight: 700, marginBottom: 10 }}>{v.title}</h3>
                <p style={{ color: 'var(--slate)', fontSize: 13, lineHeight: 1.75 }}>{v.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Timeline ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">Timeline</p>
            <h2 className="section-title">How We Got Here</h2>
          </div>
          <div style={{ maxWidth: 720, margin: '48px auto 0', display: 'flex', flexDirection: 'column', gap: 0 }}>
            {MILESTONES.map((m, i) => (
              <div key={m.year} style={{ display: 'flex', gap: 24, paddingBottom: i < MILESTONES.length - 1 ? 36 : 0, position: 'relative' }}>
                {/* vertical line */}
                {i < MILESTONES.length - 1 && <div style={{ position: 'absolute', left: 5, top: 17, bottom: 0, width: 2, background: 'linear-gradient(180deg,rgba(246,166,35,0.5),transparent)' }} />}
                <div className="timeline-dot" style={{ marginTop: 6 }} />
                <div style={{ flex: 1 }}>
                  <span style={{ display: 'inline-block', fontSize: 11, fontWeight: 800, letterSpacing: '0.1em', color: 'var(--gold)', background: 'rgba(246,166,35,0.12)', border: '1px solid rgba(246,166,35,0.3)', padding: '2px 10px', borderRadius: 20, marginBottom: 8 }}>{m.year}</span>
                  <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 17, fontWeight: 700, marginBottom: 6 }}>{m.title}</h3>
                  <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75 }}>{m.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Team ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">The Team</p>
            <h2 className="section-title">People Behind Maljani</h2>
            <p style={{ color: 'var(--slate)', maxWidth: 520, margin: '0 auto' }}>Insurance professionals, engineers, and operators who've lived the problem we're solving.</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : tablet ? 'repeat(2,1fr)' : 'repeat(4,1fr)', gap: 20, marginTop: 48 }}>
            {TEAM.map(t => (
              <div key={t.name} style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '28px 22px', textAlign: 'center' }}>
                <div style={{ width: 64, height: 64, borderRadius: '50%', background: 'var(--glass-bg-md)', border: '1px solid var(--glass-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 30, margin: '0 auto 16px' }}>{t.emoji}</div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 16, marginBottom: 4 }}>{t.name}</div>
                <div style={{ fontSize: 12, color: 'var(--gold)', fontWeight: 600, marginBottom: 12 }}>{t.role}</div>
                <p style={{ color: 'var(--slate)', fontSize: 12.5, lineHeight: 1.7 }}>{t.note}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 120px' }}>
        <div className="container">
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: 20 }}>
            <div style={{ background: 'linear-gradient(135deg,var(--indigo),var(--indigo-glow))', borderRadius: 'var(--radius-xl)', padding: '52px 44px', position: 'relative', overflow: 'hidden' }}>
              <div style={{ position: 'absolute', top: -30, right: -30, width: 180, height: 180, borderRadius: '50%', background: 'rgba(255,255,255,0.06)', pointerEvents: 'none' }} />
              <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.14em', textTransform: 'uppercase', opacity: 0.7, marginBottom: 12 }}>Travelers</p>
              <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 26, fontWeight: 800, marginBottom: 14, lineHeight: 1.2 }}>Get Covered in 4 Minutes.</h3>
              <p style={{ opacity: 0.82, fontSize: 14, lineHeight: 1.75, marginBottom: 28 }}>Compare 15+ policies and get your certificate instantly. No account required.</p>
              <button className="btn btn--gold btn--lg" onClick={() => onNavigate('catalog')}>Compare Policies &rarr;</button>
            </div>
            <div style={{ background: 'linear-gradient(135deg,rgba(49,99,49,0.3),rgba(49,99,49,0.12))', border: '1px solid rgba(49,99,49,0.4)', borderRadius: 'var(--radius-xl)', padding: '52px 44px', position: 'relative', overflow: 'hidden' }}>
              <div style={{ position: 'absolute', top: -30, right: -30, width: 180, height: 180, borderRadius: '50%', background: 'rgba(49,99,49,0.1)', pointerEvents: 'none' }} />
              <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.14em', textTransform: 'uppercase', color: '#86efac', opacity: 0.9, marginBottom: 12 }}>Agencies</p>
              <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 26, fontWeight: 800, marginBottom: 14, lineHeight: 1.2 }}>Partner with Maljani.</h3>
              <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75, marginBottom: 28 }}>Join 200+ agencies issuing policies from one dashboard. Commission-based, zero fees.</p>
              <button className="btn btn--primary btn--lg" onClick={() => onNavigate('agencies')}>Learn About Agency Plans</button>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
};

export default AboutPage;
