import React, { useState, useMemo } from 'react';
import { useQuery } from 'urql';
import { useResponsive } from '../lib/useResponsive';

// Custom meta fields registered in includes/class-maljani-policy-graphql.php
const GET_POLICY_DETAIL = `
  query GetPolicyDetail($id: ID!) {
    policy(id: $id, idType: DATABASE_ID) {
      id
      databaseId
      title
      content
      excerpt
      featuredImage { node { sourceUrl } }
      policyDescription
      policyCoverDetails
      policyBenefits
      policyNotCovered
      policyCurrency
      policyPaymentDetails
      policyInsurerName
      policyInsurerLogo
      policyDayPremiums { from to premium }
      policyCountries
      regions { nodes { name slug } }
    }
  }
`;

/* Strip HTML tags and decode basic entities to plain text */
const stripHtml = (str) => {
  if (!str) return '';
  return str
    .replace(/<[^>]*>/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&nbsp;/g, ' ')
    .replace(/\s{2,}/g, ' ')
    .trim();
};

/* Split an HTML or plain-text string into non-empty bullet lines */
const toLines = (str) =>
  (str || '')
    .split(/<\/p>|<\/li>|\r?\n/i)
    .map(l => stripHtml(l))
    .filter(Boolean);

const Section = ({ title, accent, children }) => (
  <div style={{ marginTop: 36, paddingTop: 36, borderTop: '1px solid var(--glass-border)' }}>
    <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 700, marginBottom: 18 }}>
      {title}{accent && <span style={{ color: 'var(--gold)', marginLeft: 6 }}>{accent}</span>}
    </h3>
    {children}
  </div>
);

/** Days between two YYYY-MM-DD strings, inclusive */
const tripDays = (dep, ret) => {
  if (!dep || !ret) return 0;
  const ms = new Date(ret) - new Date(dep);
  return Math.max(1, Math.round(ms / 86400000) + 1);
};

/** Find premium for given trip length from bracket array */
const bracketPremium = (brackets, days) => {
  if (!brackets?.length || !days) return null;
  const match = brackets.find(b => days >= b.from && days <= b.to);
  return match ? match.premium : null;
};

const PolicyDetail = ({ policyId, searchData, onBack, onStartWizard }) => {
  const { mobile, tablet } = useResponsive();
  const today    = new Date().toISOString().split('T')[0];
  const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0];

  const [departure,  setDeparture]  = useState(searchData?.departure  || today);
  const [returnDate, setReturnDate] = useState(searchData?.returnDate || nextWeek);
  const [passengers, setPassengers] = useState(searchData?.passengers || 1);

  const [{ data, fetching, error }] = useQuery({
    query: GET_POLICY_DETAIL,
    variables: { id: String(policyId) },
    pause: !policyId,
  });

  const policy = data?.policy;

  if (fetching) return (
    <div style={{ padding: '10rem 0', textAlign: 'center', color: 'var(--slate)' }}>
      <div style={{ width: 40, height: 40, border: '3px solid var(--indigo)', borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 20px' }} />
      <style>{`@keyframes spin{to{transform:rotate(360deg)}}`}</style>
      Loading policy…
    </div>
  );

  if (error || !policy) return (
    <div style={{ padding: '10rem 0', textAlign: 'center' }}>
      <p style={{ color: '#f87171', marginBottom: 20 }}>
        {error ? `Error: ${error.message}` : 'Policy not found.'}
      </p>
      <button className="btn btn--ghost" onClick={onBack}>← Back</button>
    </div>
  );

  const currency      = policy.policyCurrency || 'KES';
  const exclusions    = toLines(policy.policyNotCovered);
  const coverDetails  = toLines(policy.policyCoverDetails);
  const premiums      = policy.policyDayPremiums || [];
  const regions       = policy.regions?.nodes || [];
  const countries     = policy.policyCountries || [];
  const lowestPremium = premiums.length ? Math.min(...premiums.map(p => p.premium)) : null;

  // Live quote calculation from sidebar date pickers
  const quoteDays    = tripDays(departure, returnDate);
  const quotePremium = bracketPremium(premiums, quoteDays);
  const quoteTotal   = quotePremium !== null ? quotePremium * passengers : null;
  const fmtKES = (n) => `${currency} ${Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

  return (
    <div className="fade-in" style={{ paddingBottom: '6rem' }}>

      {/* â”€â”€ Hero â”€â”€ */}
      <section style={{
        position: 'relative', height: mobile ? 280 : 360,
        background: policy.featuredImage?.node?.sourceUrl
          ? `url(${policy.featuredImage.node.sourceUrl}) center/cover`
          : 'linear-gradient(135deg,var(--indigo),#1e1b4b)',
        borderRadius: '0 0 32px 32px', overflow: 'hidden', marginBottom: 48,
      }}>
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to bottom, rgba(8,14,39,0.3), rgba(8,14,39,0.88))' }} />
        <div style={{ position: 'absolute', bottom: 36, left: 0, right: 0 }} className="container">
          <button onClick={onBack} style={{ background: 'none', border: 'none', color: 'rgba(255,255,255,0.55)', fontSize: 13, cursor: 'pointer', marginBottom: 14, display: 'flex', alignItems: 'center', gap: 6, padding: 0, fontFamily: 'var(--font-body)' }}>
            ← Back to policies
          </button>
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 18, flexWrap: 'wrap' }}>
            {policy.policyInsurerLogo && (
              <img src={policy.policyInsurerLogo} alt={policy.policyInsurerName} style={{ width: 52, height: 52, borderRadius: 12, objectFit: 'contain', background: '#fff', padding: 4, flexShrink: 0 }} />
            )}
            <div>
              {policy.policyInsurerName && (
                <p style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--gold)', margin: '0 0 6px' }}>
                  {policy.policyInsurerName}
                </p>
              )}
              <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(24px,4vw,40px)', fontWeight: 800, color: '#fff', margin: 0 }}>
                {policy.title}
              </h1>
            </div>
          </div>
          {regions.length > 0 && (
            <div style={{ display: 'flex', gap: 8, marginTop: 14, flexWrap: 'wrap' }}>
              {regions.map(r => (
                <span key={r.slug} style={{ padding: '4px 12px', background: 'rgba(49,99,49,0.25)', border: '1px solid rgba(49,99,49,0.4)', borderRadius: 100, fontSize: 12, fontWeight: 600, color: '#86efac' }}>
                  {r.name}
                </span>
              ))}
            </div>
          )}
        </div>
      </section>

      <div className="container">
        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : tablet ? '1fr 300px' : '1fr 340px', gap: mobile ? 24 : 40, alignItems: 'start' }}>

          {/* â”€â”€ Main column â”€â”€ */}
          <div className="glass-card" style={{ padding: '2.5rem' }}>

            {/* Description / excerpt */}
            {(policy.policyDescription || policy.excerpt) && (
              <div style={{ color: 'var(--slate)', lineHeight: 1.8, fontSize: 15 }}
                dangerouslySetInnerHTML={{ __html: policy.policyDescription || policy.excerpt }} />
            )}

            {/* Full content (WP editor body) */}
            {policy.content && (
              <Section title="Policy" accent="Details">
                <div style={{ color: 'var(--slate)', lineHeight: 1.8, fontSize: 14 }}
                  dangerouslySetInnerHTML={{ __html: policy.content }} />
              </Section>
            )}

            {/* Cover Details */}
            {coverDetails.length > 0 && (
              <Section title="What's" accent="Covered">
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(220px,1fr))', gap: 10 }}>
                  {coverDetails.map((item, i) => (
                    <div key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 10, padding: '12px 14px', background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-md)' }}>
                      <span style={{ color: '#34d399', fontSize: 14, flexShrink: 0, marginTop: 1 }}>✔</span>
                      <span style={{ fontSize: 13, color: 'rgba(255,255,255,0.8)', lineHeight: 1.5 }}>{item}</span>
                    </div>
                  ))}
                </div>
              </Section>
            )}

            {/* Benefits */}
            {policy.policyBenefits && (
              <Section title="Key" accent="Benefits">
                <div 
                  className="policy-benefits-table"
                  style={{ color: 'rgba(255,255,255,0.8)', fontSize: 13, lineHeight: 1.6 }}
                  dangerouslySetInnerHTML={{ __html: policy.policyBenefits }} 
                />
              </Section>
            )}

            {/* Exclusions */}
            {exclusions.length > 0 && (
              <Section title="Not" accent="Covered">
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
                  {exclusions.map((e, i) => (
                    <li key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 10, fontSize: 14, color: 'var(--slate)', lineHeight: 1.6 }}>
                      <span style={{ color: '#f87171', fontSize: 13, flexShrink: 0, marginTop: 2 }}>✕</span>
                      {e}
                    </li>
                  ))}
                </ul>
              </Section>
            )}

            {/* Premium schedule */}
            {premiums.length > 0 && (
              <Section title="Premium" accent="Schedule">
                <div style={{ overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                    <thead>
                      <tr>
                        {['Trip Duration', `Premium (${currency})`].map(h => (
                          <th key={h} style={{ padding: '10px 14px', textAlign: 'left', borderBottom: '1px solid var(--glass-border)', color: 'var(--slate)', fontWeight: 700, fontSize: 11, letterSpacing: '0.08em', textTransform: 'uppercase' }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {premiums.map((p, i) => (
                        <tr key={i} style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                          <td style={{ padding: '10px 14px', color: 'rgba(255,255,255,0.8)' }}>{p.from}–{p.to} days</td>
                          <td style={{ padding: '10px 14px', fontWeight: 700, color: 'var(--gold)' }}>{currency} {p.premium.toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </Section>
            )}

            {/* Payment details */}
            {policy.policyPaymentDetails && (
              <Section title="Payment" accent="Details">
                <div style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.8 }}
                  dangerouslySetInnerHTML={{ __html: policy.policyPaymentDetails }} />
              </Section>
            )}

          </div>

          {/* â”€â”€ Sidebar â”€â”€ */}
          <aside style={{ display: 'flex', flexDirection: 'column', gap: 20, position: 'sticky', top: 100 }}>

            {/* Quote CTA */}
            <div className="glass-card" style={{ padding: '2rem', border: '1px solid rgba(49,99,49,0.4)' }}>
              <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--gold)', margin: '0 0 14px' }}>Your Trip Quote</p>

              {/* Date pickers */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 12 }}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                  <label style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: 'rgba(255,255,255,0.5)' }}>Departure</label>
                  <input
                    type="date"
                    min={today}
                    value={departure}
                    onChange={e => {
                      setDeparture(e.target.value);
                      if (returnDate < e.target.value) setReturnDate(e.target.value);
                    }}
                    style={{ padding: '8px 10px', borderRadius: 7, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: '#fff', fontSize: 13, fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box' }}
                  />
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                  <label style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: 'rgba(255,255,255,0.5)' }}>Return</label>
                  <input
                    type="date"
                    min={departure}
                    value={returnDate}
                    onChange={e => setReturnDate(e.target.value)}
                    style={{ padding: '8px 10px', borderRadius: 7, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: '#fff', fontSize: 13, fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box' }}
                  />
                </div>
                {/* Travellers */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                  <label style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: 'rgba(255,255,255,0.5)' }}>Travellers</label>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <button type="button" onClick={() => setPassengers(p => Math.max(1, p - 1))}
                      style={{ width: 28, height: 28, borderRadius: 6, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: '#fff', cursor: 'pointer', fontSize: 16, lineHeight: 1 }}>−</button>
                    <span style={{ fontWeight: 800, minWidth: 18, textAlign: 'center' }}>{passengers}</span>
                    <button type="button" onClick={() => setPassengers(p => p + 1)}
                      style={{ width: 28, height: 28, borderRadius: 6, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: '#fff', cursor: 'pointer', fontSize: 16, lineHeight: 1 }}>+</button>
                  </div>
                </div>
              </div>

              {/* Live premium result */}
              {quoteDays > 0 && (
                <div style={{ padding: '12px 14px', borderRadius: 8, background: quoteTotal !== null ? 'rgba(212,175,55,0.08)' : 'rgba(255,255,255,0.03)', border: '1px solid ' + (quoteTotal !== null ? 'rgba(212,175,55,0.25)' : 'var(--glass-border)'), marginBottom: 14, textAlign: 'center' }}>
                  {quoteTotal !== null ? (
                    <>
                      <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--gold)' }}>{fmtKES(quoteTotal)}</div>
                      <div style={{ fontSize: 11, color: 'var(--slate)', marginTop: 3 }}>{quoteDays} day{quoteDays !== 1 ? 's' : ''} · {fmtKES(quotePremium)} × {passengers} traveller{passengers !== 1 ? 's' : ''}</div>
                    </>
                  ) : (
                    <>
                      <div style={{ fontSize: 13, color: 'var(--slate)' }}>No bracket for {quoteDays} days</div>
                      <div style={{ fontSize: 11, color: 'var(--slate)', opacity: 0.7, marginTop: 3 }}>Contact us for a custom quote</div>
                    </>
                  )}
                </div>
              )}

              {lowestPremium !== null && !quoteDays && (
                <>
                  <p style={{ fontSize: 11, color: 'var(--slate)', margin: '0 0 4px' }}>Starting from</p>
                  <div style={{ fontSize: 28, fontWeight: 800, color: '#fff', marginBottom: 14 }}>
                    {fmtKES(lowestPremium)}
                  </div>
                </>
              )}

              <button
                className="btn btn--primary"
                style={{ width: '100%', justifyContent: 'center' }}
                onClick={() => onStartWizard(policy.databaseId, { departure, returnDate, passengers, region: regions[0]?.slug }, 3)}>
                Get This Policy →
              </button>
            </div>

            {/* Quick specs */}
            <div className="glass-card" style={{ padding: '1.75rem' }}>
              <h4 style={{ fontFamily: 'var(--font-display)', fontSize: 14, fontWeight: 700, marginBottom: 16, color: 'rgba(255,255,255,0.9)' }}>Quick Info</h4>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                  <span style={{ color: 'var(--slate)' }}>Policy ID</span>
                  <span style={{ fontWeight: 600 }}>#{policy.databaseId}</span>
                </div>
                {policy.policyInsurerName && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                    <span style={{ color: 'var(--slate)' }}>Underwriter</span>
                    <span style={{ fontWeight: 600, textAlign: 'right', maxWidth: 160 }}>{policy.policyInsurerName}</span>
                  </div>
                )}
                {currency && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                    <span style={{ color: 'var(--slate)' }}>Currency</span>
                    <span style={{ fontWeight: 600 }}>{currency}</span>
                  </div>
                )}
                {regions.length > 0 && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, gap: 12 }}>
                    <span style={{ color: 'var(--slate)', flexShrink: 0 }}>Regions</span>
                    <span style={{ fontWeight: 600, textAlign: 'right' }}>{regions.map(r => r.name).join(', ')}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Countries covered */}
            {countries.length > 0 && (
              <div className="glass-card" style={{ padding: '1.75rem' }}>
                <h4 style={{ fontFamily: 'var(--font-display)', fontSize: 14, fontWeight: 700, marginBottom: 12, color: 'rgba(255,255,255,0.9)' }}>Countries Covered</h4>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
                  {countries.map(c => (
                    <span key={c} style={{ fontSize: 11, padding: '3px 9px', borderRadius: 100, background: 'rgba(49,99,49,0.15)', border: '1px solid rgba(49,99,49,0.25)', color: '#86efac' }}>{c}</span>
                  ))}
                </div>
              </div>
            )}

          </aside>
        </div>
      </div>
    </div>
  );
};

export default PolicyDetail;
