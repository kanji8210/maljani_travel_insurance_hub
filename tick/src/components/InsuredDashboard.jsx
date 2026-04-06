import React, { useState } from 'react';
import { useQuery, gql } from 'urql';
import { useAuth } from '../lib/AuthContext';
import { useResponsive } from '../lib/useResponsive';
import ProfileEditModal from './ProfileEditModal';
import NotificationPanel from './NotificationPanel';

const MY_POLICY_SALES = gql`
  query MyPolicySales {
    myPolicySales {
      id
      policyId
      policyTitle
      policyNumber
      region
      premium
      days
      departure
      returnDate
      passengers
      insuredNames
      insuredEmail
      insuredPhone
      passportNumber
      amountPaid
      paymentStatus
      policyStatus
      createdAt
    }
  }
`;

const POLICY_STATUS = {
  active:      { label: 'Active',     color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
  confirmed:   { label: 'Confirmed',  color: '#60a5fa', bg: 'rgba(96,165,250,0.12)' },
  approved:    { label: 'Approved',   color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
  unconfirmed: { label: 'Pending',    color: '#94a3b8', bg: 'rgba(148,163,184,0.1)' },
  expired:     { label: 'Expired',    color: '#f87171', bg: 'rgba(248,113,113,0.1)' },
};
const getPS = (s) => POLICY_STATUS[s] ?? POLICY_STATUS.unconfirmed;

const PAY_STATUS = {
  paid:    { label: 'Paid',    color: '#22c55e' },
  pending: { label: 'Pending', color: '#f59e0b' },
  failed:  { label: 'Failed',  color: '#f87171' },
};
const getPay = (s) => PAY_STATUS[s] ?? { label: (s || 'Unknown'), color: '#94a3b8' };

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const fmtKES  = (n) => `KES ${Number(n || 0).toLocaleString('en-KE')}`;

import Messaging from './Messaging';

const InsuredDashboard = ({ user, onNavigate }) => {
  const { user: authUser } = useAuth();
  const { mobile, tablet } = useResponsive();
  const [activeTab, setActiveTab] = useState('policies');
  const [selectedPolicyId, setSelectedPolicyId] = useState(null);
  const [actionLoading, setActionLoading] = useState(null); // tracks which sale ID has a loading action
  const [showProfileEdit, setShowProfileEdit] = useState(false);

  const [result, reexecuteQuery] = useQuery({ query: MY_POLICY_SALES, pause: !authUser?.token });
  const loading  = result.fetching;
  const fetchErr = result.error ? 'Could not load your policies. Please refresh the page.' : null;
  const policies = result.data?.myPolicySales || [];

  const WP_REST_BASE = import.meta.env.VITE_GRAPHQL_URL
    ? import.meta.env.VITE_GRAPHQL_URL.replace(/\/graphql$/, '') + '/wp-json'
    : '/wp-json';

  /** Open invoice in a new tab */
  const handleViewInvoice = async (saleId) => {
    setActionLoading(saleId);
    try {
      const res = await fetch(`${WP_REST_BASE}/maljani/v1/invoice/${saleId}?doc_type=invoice`, {
        headers: { Authorization: `Bearer ${authUser.token}` },
      });
      if (!res.ok) throw new Error('Failed to load invoice');
      const data = await res.json();
      const win = window.open('', '_blank');
      if (win) { win.document.write(data.html); win.document.close(); }
    } catch (e) {
      alert('Could not load the invoice. Please try again.');
    } finally {
      setActionLoading(null);
    }
  };

  /** Redirect to Pesapal payment page */
  const handleProceedToPayment = async (saleId) => {
    setActionLoading(saleId);
    try {
      const res = await fetch(`${WP_REST_BASE}/maljani/v1/initiate-payment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${authUser.token}`,
        },
        body: JSON.stringify({ saleId }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Payment initiation failed');
      if (data.paymentUrl) {
        window.location.href = data.paymentUrl;
      }
    } catch (e) {
      alert(e.message || 'Could not start payment. Please try again.');
      setActionLoading(null);
    }
  };

  /** Navigate to edit page (re-enter wizard with pre-filled data) */
  const handleEditPolicy = (p) => {
    onNavigate('wizard', null, {
      editSaleId: p.id,
      policyId: p.policyId,
      region: p.region,
      departure: p.departure,
      returnDate: p.returnDate,
      passengers: p.passengers,
    });
  };

  const activePolicies  = policies.filter(p => ['active', 'confirmed', 'approved'].includes(p.policyStatus));
  const pendingPolicies = policies.filter(p => p.paymentStatus === 'pending');

  const STATS = [
    { icon: '🗂️', val: policies.length,        lbl: 'Total Policies'  },
    { icon: '✅', val: activePolicies.length,   lbl: 'Active'          },
    { icon: '⏳', val: pendingPolicies.length,  lbl: 'Pending Payment' },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 22 }}>
      {showProfileEdit && <ProfileEditModal onClose={() => setShowProfileEdit(false)} />}
      <style>{`
        .pd-card { background:var(--glass-bg); border:1px solid var(--glass-border); border-radius:var(--radius-lg); transition:border-color 0.2s, transform 0.2s; }
        .pd-card:hover { border-color:rgba(246,166,35,0.3); transform:translateY(-2px); }
        .qa-btn { display:block; width:100%; text-align:left; background:rgba(255,255,255,0.04); border:1px solid var(--glass-border); border-radius:8px; padding:10px 14px; color:rgba(255,255,255,0.8); font-size:13px; font-family:var(--font-body); cursor:pointer; margin-bottom:8px; transition:background 0.15s; }
        .qa-btn:hover { background:rgba(255,255,255,0.09); }
        .tab-btn { background:none; border:none; color:var(--slate); font-family:var(--font-display); font-size:14px; font-weight:700; padding:8px 0; margin-right:${mobile ? '16px' : '32px'}; cursor:pointer; position:relative; transition:color 0.2s; }
        .tab-btn.active { color:#fff; }
        .tab-btn.active::after { content:''; position:absolute; bottom:0; left:0; width:100%; height:2px; background:var(--gold); }
      `}</style>

      {/* Stats row */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 14, flexWrap: 'wrap' }}>
        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : 'repeat(3,1fr)', gap: 14, flex: 1 }}>
        {STATS.map(({ icon, val, lbl }) => (
          <div key={lbl} style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '22px 26px', display: 'flex', alignItems: 'center', gap: 14 }}>
            <span style={{ fontSize: 26 }}>{icon}</span>
            <div>
              <div style={{ fontFamily: 'var(--font-display)', fontSize: 30, fontWeight: 800, lineHeight: 1, color: loading ? 'var(--slate)' : '#fff' }}>
                {loading ? '—' : val}
              </div>
              <div style={{ fontSize: 11, color: 'var(--slate)', marginTop: 4, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{lbl}</div>
            </div>
          </div>
        ))}
        </div>
        {/* Notification bell */}
        <NotificationPanel onNavigate={onNavigate} />
      </div>

      {/* Tab Swticher */}
      <div style={{ borderBottom: '1px solid var(--glass-border)', marginBottom: 8, display: 'flex' }}>
        <button className={`tab-btn ${activeTab === 'policies' ? 'active' : ''}`} onClick={() => { setActiveTab('policies'); setSelectedPolicyId(null); }}>MY POLICIES</button>
        <button className={`tab-btn ${activeTab === 'support' ? 'active' : ''}`} onClick={() => { setActiveTab('support'); setSelectedPolicyId(null); }}>
          LIVE SUPPORT 
          {/* Subtle indicator if needed */}
        </button>
      </div>

      {/* Main layout: policies/support + sidebar */}
      <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 300px', gap: 24, alignItems: 'start' }}>

        {/* LEFT: Tab Content */}
        <div>
          {activeTab === 'policies' ? (
            <>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 700 }}>Active Coverage</h3>
                <button 
                  className="btn btn--primary btn--sm" 
                  style={{ padding: '10px 20px', boxShadow: '0 4px 14px rgba(49,99,49,0.3)' }}
                  onClick={() => onNavigate('catalog')}
                >+ GET NEW POLICY</button>
              </div>

              {/* Loading */}
              {loading && (
                <div style={{ padding: '52px 0', textAlign: 'center', color: 'var(--slate)' }}>
                  <div style={{ fontSize: 30, marginBottom: 12 }}>⏳</div>
                  <p style={{ fontSize: 14 }}>Loading your policies…</p>
                </div>
              )}

              {/* Error */}
              {fetchErr && !loading && (
                <div style={{ padding: '14px 16px', background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.25)', borderRadius: 10, color: '#fca5a5', fontSize: 13, marginBottom: 16 }}>
                  ⚠️ {fetchErr}
                </div>
              )}

              {/* Empty */}
              {!loading && !fetchErr && policies.length === 0 && (
                <div style={{ padding: '52px 24px', textAlign: 'center', background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)' }}>
                  <div style={{ fontSize: 42, marginBottom: 14 }}>✈️</div>
                  <p style={{ color: 'var(--slate)', marginBottom: 20, fontSize: 15 }}>You haven't purchased any policies yet.</p>
                  <button className="btn btn--primary" onClick={() => onNavigate('catalog')}>Browse Policies →</button>
                </div>
              )}

              {/* Policy cards */}
              {!loading && policies.map(p => {
                const isPending = p.paymentStatus === 'pending' || p.policyStatus === 'unconfirmed';
                const ps  = getPS(p.policyStatus);
                const pay = getPay(p.paymentStatus);
                
                return (
                  <div key={p.id} className="pd-card" style={{ 
                    padding: '24px', marginBottom: 16,
                    borderLeft: `4px solid ${isPending ? 'var(--gold)' : '#22c55e'}`,
                    background: isPending ? 'rgba(212,175,55,0.03)' : 'var(--glass-bg)'
                  }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 16 }}>
                      <div style={{ flex: 1, minWidth: 200 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 6 }}>
                          <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--gold)', letterSpacing: '0.08em', textTransform: 'uppercase' }}>
                            {p.policyTitle}
                          </span>
                          {isPending && <span style={{ fontSize: 10, background: 'var(--gold)', color: '#000', padding: '1px 6px', borderRadius: 4, fontWeight: 800 }}>ACTION REQUIRED</span>}
                        </div>
                        
                        <div style={{ fontFamily: 'var(--font-display)', fontSize: 18, fontWeight: 800, marginBottom: 10, color: '#fff' }}>
                          #{p.policyNumber}
                        </div>
                        
                        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap', fontSize: 13, color: 'var(--slate)' }}>
                          <span>📍 {p.region || 'Worldwide'}</span>
                          <span>📅 {fmtDate(p.departure)} – {fmtDate(p.returnDate)}</span>
                          <span>👤 {p.passengers} traveller{p.passengers !== 1 ? 's' : ''}</span>
                        </div>

                        {isPending && (
                          <div style={{ marginTop: 18, display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
                            <button
                              className="btn btn--primary btn--sm"
                              style={{ padding: '8px 16px', fontSize: 12 }}
                              disabled={actionLoading === p.id}
                              onClick={() => handleProceedToPayment(p.id)}
                            >
                              {actionLoading === p.id ? 'Processing…' : 'Proceed to Payment →'}
                            </button>
                            <button
                              onClick={() => handleViewInvoice(p.id)}
                              disabled={actionLoading === p.id}
                              style={{ background: 'none', border: 'none', color: 'var(--gold)', fontSize: 12, cursor: 'pointer', textDecoration: 'underline', fontFamily: 'var(--font-body)', padding: 0 }}
                            >
                              View Invoice
                            </button>
                            <button
                              onClick={() => handleEditPolicy(p)}
                              style={{ background: 'none', border: '1px solid var(--glass-border)', borderRadius: 6, color: 'var(--slate)', fontSize: 11, cursor: 'pointer', padding: '4px 10px', fontFamily: 'var(--font-body)', transition: 'all 0.2s' }}
                              onMouseOver={(e) => { e.currentTarget.style.color = '#fff'; e.currentTarget.style.borderColor = 'var(--gold)'; }}
                              onMouseOut={(e) => { e.currentTarget.style.color = 'var(--slate)'; e.currentTarget.style.borderColor = 'var(--glass-border)'; }}
                            >
                              ✏️ Edit
                            </button>
                          </div>
                        )}
                      </div>

                      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 8, flexShrink: 0 }}>
                        <div style={{ background: ps.bg, color: ps.color, border: `1px solid ${ps.color}44`, borderRadius: 7, padding: '4px 12px', fontSize: 11, fontWeight: 800, letterSpacing: '0.06em' }}>
                          {ps.label.toUpperCase()}
                        </div>
                        <div style={{ fontSize: 18, fontWeight: 800, color: '#fff' }}>{fmtKES(p.amountPaid)}</div>
                        <div style={{ fontSize: 11, color: pay.color, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 4 }}>
                          <span style={{ width: 6, height: 6, borderRadius: '50%', background: pay.color }}></span>
                          Payment: {pay.label}
                        </div>
                        <button 
                          onClick={() => { setSelectedPolicyId(p.id); setActiveTab('support'); }}
                          style={{ marginTop: 10, background: 'rgba(255,255,255,0.05)', border: '1px solid var(--glass-border)', borderRadius: 6, padding: '6px 10px', color: 'var(--slate)', fontSize: 11, fontWeight: 600, cursor: 'pointer', transition: 'all 0.2s' }}
                          onMouseOver={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.1)'; e.currentTarget.style.color = '#fff'; }}
                          onMouseOut={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.05)'; e.currentTarget.style.color = 'var(--slate)'; }}
                        >
                          💬 Policy Support
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </>
          ) : (
            <div className="fade-in">
              <Messaging initialPolicyId={selectedPolicyId} />
            </div>
          )}
        </div>

        {/* RIGHT: Sidebar */}
        <aside style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>

          {/* Profile card */}
          <div style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '26px 22px', textAlign: 'center' }}>
            <div style={{ width: 60, height: 60, borderRadius: '50%', background: 'linear-gradient(135deg,var(--indigo),var(--indigo-glow))', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 24, fontWeight: 800, margin: '0 auto 14px', color: '#fff', fontFamily: 'var(--font-display)' }}>
              {user?.name?.charAt(0)?.toUpperCase() || 'T'}
            </div>
            <div style={{ fontFamily: 'var(--font-display)', fontSize: 17, fontWeight: 700, marginBottom: 4 }}>{user?.name}</div>
            <div style={{ fontSize: 11, color: 'var(--gold)', fontWeight: 700, marginBottom: 10, letterSpacing: '0.08em', textTransform: 'uppercase' }}>Traveler Account</div>
            <div style={{ fontSize: 12, color: 'var(--slate)' }}>{user?.email}</div>
            {user?.phone && <div style={{ fontSize: 12, color: 'var(--slate)', marginTop: 4 }}>📞 {user.phone}</div>}
            <button
              type="button"
              onClick={() => setShowProfileEdit(true)}
              style={{
                marginTop: 14, width: '100%', padding: '9px 0', borderRadius: 8,
                border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.04)',
                color: 'rgba(255,255,255,0.75)', fontSize: 12, fontWeight: 700,
                fontFamily: 'var(--font-body)', cursor: 'pointer', transition: 'all 0.2s',
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
              }}
              onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--gold)'; e.currentTarget.style.color = '#fff'; }}
              onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--glass-border)'; e.currentTarget.style.color = 'rgba(255,255,255,0.75)'; }}
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Edit Profile
            </button>
          </div>

          {/* Quick actions */}
          <div style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '20px' }}>
            <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--gold)', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 14 }}>Quick Actions</div>
            <button className="qa-btn" onClick={() => onNavigate('catalog')}>🛡️ Compare &amp; Buy Policy</button>
            <button className="qa-btn" onClick={() => onNavigate('verify')}>🔍 Verify a Certificate</button>
          </div>

          {/* Verified badge */}
          <div style={{ background: 'linear-gradient(135deg,rgba(49,99,49,0.16),rgba(34,197,94,0.05))', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 'var(--radius-lg)', padding: '20px' }}>
            <div style={{ fontSize: 22, marginBottom: 8 }}>🛡️</div>
            <div style={{ fontSize: 13, fontWeight: 700, color: '#86efac', marginBottom: 6 }}>Embassy-Verified</div>
            <p style={{ fontSize: 12, color: 'var(--slate)', lineHeight: 1.6 }}>
              Your certificates are cryptographically signed. Any embassy can confirm authenticity at verify.maljani.co.ke.
            </p>
          </div>

        </aside>
      </div>
    </div>
  );
};

export default InsuredDashboard;
