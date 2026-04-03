import React, { useState } from 'react';

const FAQS = [
  { q: 'Which destinations are covered?',            a: 'Maljani covers East Africa, Southern Africa, West Africa, Schengen/Europe, Middle East, Asia, and Worldwide plans. Your selected destination auto-filters policies from our network of 15+ verified insurers.' },
  { q: 'How fast is my certificate issued?',         a: 'Certificates are generated in under 3 minutes after payment confirmation. You receive a downloadable PDF immediately and an email copy within 5 minutes. QR-verified embassy letters are included at no extra cost.' },
  { q: 'Is Maljani itself a licensed insurer?',      a: 'No. Maljani is a licensed insurance aggregation and distribution platform. All policies are underwritten directly by IRA-approved insurance companies. We are a regulated intermediary — not the risk carrier.' },
  { q: 'Does the policy meet Schengen requirements?', a: 'Yes. Policies labelled “Schengen Approved” meet the EU minimum of â‚¬30,000 medical cover. Our embassy letters are accepted at all Schengen embassies operating in East Africa.' },
  { q: 'Can I buy group or family policies?',        a: 'Yes. Select “Group” on the quote card to compare prices for 2–20+ travellers. Travel agencies can issue bulk policies from their partner dashboard.' },
  { q: 'How do I make a claim?',                     a: 'Claims are filed directly with the underwriting insurer using your policy number. Your Maljani certificate includes the insurer’s 24/7 emergency hotline and claims portal URL.' },
];

const FAQSection = () => {
  const [open, setOpen] = useState(null);
  const toggle = (i) => setOpen(open === i ? null : i);

  return (
    <section style={{ position: 'relative', zIndex: 1, padding: '0 0 110px' }}>
      <div className="container">
        <div className="section-header">
          <p className="section-label">Common Questions</p>
          <h2 className="section-title reveal">Everything You Need to Know</h2>
          <p className="reveal reveal-delay-1">Clear answers on coverage, pricing, certificates, and claims.</p>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, maxWidth: 1040, margin: '0 auto' }}>
          {FAQS.map((f, i) => (
            <div key={i} style={{ background: 'var(--glass-bg)', border: `1px solid ${open === i ? 'rgba(49,99,49,0.38)' : 'var(--glass-border)'}`, borderRadius: 'var(--radius-md)', overflow: 'hidden', transition: 'border-color 0.25s' }}>
              <button
                onClick={() => toggle(i)}
                aria-expanded={open === i}
                style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: '20px 22px', background: 'none', border: 'none', color: open === i ? '#86efac' : 'var(--white)', fontFamily: 'var(--font-body)', fontSize: 15, fontWeight: 600, textAlign: 'left', cursor: 'pointer', transition: 'color 0.2s' }}
              >
                {f.q}
                <span style={{ flexShrink: 0, fontSize: 20, color: open === i ? 'var(--indigo-glow)' : 'var(--slate)', transform: open === i ? 'rotate(180deg)' : 'none', transition: 'transform 0.3s, color 0.2s', lineHeight: 1 }} aria-hidden="true">⌄</span>
              </button>
              <div style={{ maxHeight: open === i ? 200 : 0, overflow: 'hidden', transition: 'max-height 0.4s cubic-bezier(0.4,0,0.2,1)', padding: open === i ? '0 22px 20px' : '0 22px' }}>
                <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75 }}>{f.a}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default FAQSection;
