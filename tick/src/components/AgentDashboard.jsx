import React, { useState, useMemo } from 'react';
import { useQuery, gql } from 'urql';
import { useAuth } from '../lib/AuthContext';
import { useResponsive } from '../lib/useResponsive';
import ProfileEditModal from './ProfileEditModal';
import NotificationPanel from './NotificationPanel';
import PolicyEditModal from './PolicyEditModal';
import AssignClientModal from './AssignClientModal';

/* ═══════════════════════════════════════════════════════════════════
 *  GRAPHQL
 * ═══════════════════════════════════════════════════════════════════ */

const AGENCY_DASHBOARD = gql`
  query AgencyDashboard {
    agencyDashboard {
      agency { id name contactName contactEmail contactPhone commissionRate status createdAt }
      stats {
        totalPolicies activePolicies pendingPolicies
        totalPremiumVolume totalCommissionEarned pendingCommission disputedCommission
        totalClients conversionRate
      }
      clients { email name phone policiesCount totalPremium lastActivity hasActive }
      monthlyAnalytics { month premium commission policies clients }
      statusDistribution { status count }
      topProducts { policyId policyTitle soldCount totalPremium }
    }
  }
`;

const MY_POLICIES = gql`
  query MyPolicySales {
    myPolicySales {
      id policyId policyTitle policyNumber region premium days
      departure returnDate passengers insuredNames insuredEmail insuredPhone
      passportNumber insuredDob nationalId insuredAddress countryOfOrigin
      amountPaid paymentStatus policyStatus createdAt
      workflowStatus agentCommissionAmount agentCommissionStatus
      serviceFeeAmount maljaniCommissionAmount netToInsurer
    }
  }
`;

/* ═══════════════════════════════════════════════════════════════════
 *  HELPERS
 * ═══════════════════════════════════════════════════════════════════ */

const fmtKES  = (n) => `KES ${Number(n || 0).toLocaleString('en-KE')}`;
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const fmtMonth = (m) => {
  const [y, mo] = (m || '').split('-');
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return months[parseInt(mo, 10) - 1] + ' ' + y;
};

const POLICY_STATUS = {
  active:           { label: 'Active',     color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
  verification_ready:{ label: 'Verified', color: '#3b82f6', bg: 'rgba(59,130,246,0.12)' },
  approved:         { label: 'Approved',   color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
  pending_review:   { label: 'In Review',  color: '#a78bfa', bg: 'rgba(167,139,250,0.12)' },
  submitted_to_insurer: { label: 'With Insurer', color: '#06b6d4', bg: 'rgba(6,182,212,0.12)' },
  unconfirmed:      { label: 'Unpaid',     color: '#94a3b8', bg: 'rgba(148,163,184,0.1)' },
  cancelled:        { label: 'Cancelled',  color: '#f87171', bg: 'rgba(248,113,113,0.1)' },
  archived:         { label: 'Archived',   color: '#64748b', bg: 'rgba(100,116,139,0.1)' },
};
const getPS = (s) => POLICY_STATUS[s] ?? { label: s || 'Unknown', color: '#94a3b8', bg: 'rgba(148,163,184,0.1)' };

const COMM_STATUS = {
  paid:     { label: 'Paid',     color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
  received: { label: 'Received', color: '#3b82f6', bg: 'rgba(59,130,246,0.12)' },
  unpaid:   { label: 'Unpaid',   color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
  disputed: { label: 'Disputed', color: '#f87171', bg: 'rgba(248,113,113,0.12)' },
};
const getComm = (s) => COMM_STATUS[s] ?? COMM_STATUS.unpaid;

const PAY_STATUS = {
  confirmed: { label: 'Paid',    color: '#22c55e' },
  pending:   { label: 'Pending', color: '#f59e0b' },
  failed:    { label: 'Failed',  color: '#f87171' },
};
const getPay = (s) => PAY_STATUS[s] ?? { label: s || '—', color: '#94a3b8' };

/* ═══════════════════════════════════════════════════════════════════
 *  MINI BAR CHART (pure SVG)
 * ═══════════════════════════════════════════════════════════════════ */

const BarChart = ({ data, dataKey, color, height = 140 }) => {
  if (!data || data.length === 0) return null;
  const max = Math.max(...data.map(d => d[dataKey] || 0), 1);
  const barW = Math.max(16, Math.min(40, (300 / data.length) - 4));
  const w = data.length * (barW + 4) + 20;
  return (
    <svg width="100%" viewBox={`0 0 ${w} ${height + 30}`} style={{ overflow: 'visible' }}>
      {data.map((d, i) => {
        const val = d[dataKey] || 0;
        const h = (val / max) * height;
        const x = 10 + i * (barW + 4);
        return (
          <g key={i}>
            <rect x={x} y={height - h} width={barW} height={h} rx={4}
              fill={color} opacity={0.85} />
            <text x={x + barW / 2} y={height + 14} textAnchor="middle"
              fill="var(--text-muted)" fontSize="8" fontFamily="var(--font-body)">
              {(d.month || '').slice(5)}
            </text>
            <title>{fmtMonth(d.month)}: {fmtKES(val)}</title>
          </g>
        );
      })}
    </svg>
  );
};

/* ═══════════════════════════════════════════════════════════════════
 *  DONUT CHART (pure SVG)
 * ═══════════════════════════════════════════════════════════════════ */

const DONUT_COLORS = ['#22c55e','#3b82f6','#f59e0b','#a78bfa','#06b6d4','#f87171','#64748b','#e879f9'];

const DonutChart = ({ data, size = 160 }) => {
  const total = data.reduce((s, d) => s + d.count, 0) || 1;
  const r = size / 2 - 12;
  const cx = size / 2;
  const cy = size / 2;
  let cumAngle = -90;

  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
      {data.map((d, i) => {
        const pct = d.count / total;
        const angle = pct * 360;
        const startAngle = cumAngle;
        cumAngle += angle;
        const endAngle = cumAngle;
        const largeArc = angle > 180 ? 1 : 0;
        const rad = Math.PI / 180;
        const x1 = cx + r * Math.cos(startAngle * rad);
        const y1 = cy + r * Math.sin(startAngle * rad);
        const x2 = cx + r * Math.cos(endAngle * rad);
        const y2 = cy + r * Math.sin(endAngle * rad);
        const color = DONUT_COLORS[i % DONUT_COLORS.length];
        if (pct < 0.005) return null;
        return (
          <path key={i}
            d={`M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`}
            fill={color} opacity={0.85} stroke="rgba(0,0,0,0.3)" strokeWidth={1}>
            <title>{d.status}: {d.count} ({(pct * 100).toFixed(0)}%)</title>
          </path>
        );
      })}
      <circle cx={cx} cy={cy} r={r * 0.55} fill="var(--navy, #0f172a)" />
      <text x={cx} y={cy - 4} textAnchor="middle" fill="white" fontSize="18" fontWeight="800" fontFamily="var(--font-display)">
        {total}
      </text>
      <text x={cx} y={cy + 12} textAnchor="middle" fill="var(--text-muted)" fontSize="8" fontFamily="var(--font-body)">
        POLICIES
      </text>
    </svg>
  );
};

/* ═══════════════════════════════════════════════════════════════════
 *  BADGE COMPONENT
 * ═══════════════════════════════════════════════════════════════════ */

const Badge = ({ label, color, bg }) => (
  <span style={{
    display: 'inline-block', padding: '0.2rem 0.6rem', borderRadius: '6px',
    fontSize: '0.68rem', fontWeight: 700, letterSpacing: '0.04em',
    color, background: bg || `${color}18`,
  }}>{label}</span>
);

/* ═══════════════════════════════════════════════════════════════════
 *  TABS
 * ═══════════════════════════════════════════════════════════════════ */

const TABS = [
  { id: 'overview',    label: 'Overview',    icon: '◎' },
  { id: 'clients',     label: 'Clients',     icon: '◉' },
  { id: 'policies',    label: 'Policies',    icon: '◈' },
  { id: 'commissions', label: 'Commissions', icon: '◆' },
  { id: 'analytics',   label: 'Analytics',   icon: '◇' },
];

/* ═══════════════════════════════════════════════════════════════════
 *  MAIN COMPONENT
 * ═══════════════════════════════════════════════════════════════════ */

const AgentDashboard = ({ user, onNavigate }) => {
  const { user: authUser } = useAuth();
  const { mobile, tablet } = useResponsive();
  const [activeTab, setActiveTab] = useState('overview');
  const [clientSearch, setClientSearch] = useState('');
  const [policyFilter, setPolicyFilter] = useState('all');
  const [commFilter, setCommFilter] = useState('all');
  const [showProfileEdit, setShowProfileEdit] = useState(false);
  const [editPolicy, setEditPolicy] = useState(null);
  const [assignPolicy, setAssignPolicy] = useState(null);
  const [clientActions, setClientActions] = useState({}); // { email: 'signalled' | 'archived' }

  const [dashResult] = useQuery({ query: AGENCY_DASHBOARD, pause: !authUser?.token });
  const [polResult]  = useQuery({ query: MY_POLICIES, pause: !authUser?.token });

  const loading = dashResult.fetching || polResult.fetching;
  const error   = dashResult.error?.message || polResult.error?.message || null;

  const dash     = dashResult.data?.agencyDashboard;
  const agency   = dash?.agency;
  const stats    = dash?.stats;
  const clients  = dash?.clients || [];
  const monthly  = dash?.monthlyAnalytics || [];
  const statusDist = dash?.statusDistribution || [];
  const topProducts = dash?.topProducts || [];
  const policies = polResult.data?.myPolicySales || [];

  /* ── filtered data ─────────────────────────────────────── */
  const filteredClients = useMemo(() => {
    let list = clients.filter(c => {
      const action = clientActions[c.email];
      if (action === 'archived') return false;
      return true;
    });
    if (clientSearch) {
      const q = clientSearch.toLowerCase();
      list = list.filter(c =>
        (c.name || '').toLowerCase().includes(q) ||
        (c.email || '').toLowerCase().includes(q) ||
        (c.phone || '').includes(q)
      );
    }
    return list;
  }, [clients, clientSearch, clientActions]);

  const filteredPolicies = useMemo(() => {
    if (policyFilter === 'all') return policies;
    return policies.filter(p => p.policyStatus === policyFilter);
  }, [policies, policyFilter]);

  const filteredCommissions = useMemo(() => {
    if (commFilter === 'all') return policies.filter(p => (p.agentCommissionAmount || 0) > 0);
    return policies.filter(p => (p.agentCommissionAmount || 0) > 0 && p.agentCommissionStatus === commFilter);
  }, [policies, commFilter]);

  /* ── shared styles ─────────────────────────────────────── */
  const cardStyle = {
    background: 'var(--glass-bg, rgba(255,255,255,0.04))',
    border: '1px solid var(--glass-border, rgba(255,255,255,0.08))',
    borderRadius: '16px',
    backdropFilter: 'blur(20px)',
  };
  const inputStyle = {
    background: 'rgba(255,255,255,0.06)',
    border: '1px solid rgba(255,255,255,0.12)',
    borderRadius: '8px', padding: '0.55rem 0.85rem',
    color: 'white', fontSize: '0.82rem', outline: 'none', width: '100%',
  };
  const thStyle = {
    padding: '0.75rem 0.6rem', fontSize: '0.68rem', fontWeight: 700,
    color: 'var(--gold, #d4af37)', letterSpacing: '0.08em', textAlign: 'left',
    borderBottom: '1px solid var(--glass-border)',
  };
  const tdStyle = { padding: '0.85rem 0.6rem', fontSize: '0.82rem', borderBottom: '1px solid rgba(255,255,255,0.04)' };

  /* ── LOADING / ERROR ───────────────────────────────────── */
  if (loading) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '60vh', gap: '0.8rem' }}>
        <div style={{ width: 28, height: 28, border: '3px solid rgba(255,255,255,0.15)', borderTopColor: 'var(--gold)', borderRadius: '50%', animation: 'spin 0.8s linear infinite' }} />
        <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Loading agency dashboard…</span>
        <style>{`@keyframes spin{to{transform:rotate(360deg)}}`}</style>
      </div>
    );
  }

  if (error) {
    return (
      <div style={{ ...cardStyle, padding: '2rem', textAlign: 'center', maxWidth: 500, margin: '4rem auto' }}>
        <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>⚠</div>
        <div style={{ color: '#f87171', fontWeight: 600, marginBottom: '0.5rem' }}>Failed to load dashboard</div>
        <div style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{error}</div>
      </div>
    );
  }

  /* ══════════════════════════════════════════════════════
   *  RENDER
   * ══════════════════════════════════════════════════════ */
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: mobile ? '1.25rem' : '1.75rem' }}>

      {/* ── HEADER BAR ────────────────────────────────── */}
      <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: '1rem' }}>
        <div>
          <h2 style={{
            fontFamily: 'var(--font-display, Syne)', fontSize: mobile ? '1.4rem' : '1.8rem',
            fontWeight: 800, margin: 0, display: 'flex', alignItems: 'center', gap: '0.6rem',
          }}>
            {agency?.name || user?.name || 'Agency'} <Badge label={agency?.status?.toUpperCase() || 'ACTIVE'} color="#22c55e" bg="rgba(34,197,94,0.12)" />
          </h2>
          <p style={{ margin: '0.3rem 0 0', color: 'var(--text-muted)', fontSize: '0.8rem' }}>
            Commission rate: <strong style={{ color: 'var(--gold)' }}>{agency?.commissionRate || 0}%</strong>
            {agency?.contactEmail && <> · {agency.contactEmail}</>}
          </p>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <NotificationPanel />
          <button onClick={() => setShowProfileEdit(true)} style={{
            background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
            borderRadius: '10px', padding: '0.5rem 1rem', color: 'white', cursor: 'pointer',
            fontSize: '0.75rem', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '0.4rem',
          }}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
            </svg>
            Edit Profile
          </button>
          <button onClick={() => onNavigate?.('wizard')} style={{
            background: 'linear-gradient(135deg, var(--gold, #d4af37), #b8941f)',
            border: 'none', borderRadius: '10px', padding: '0.55rem 1.2rem',
            color: '#0f172a', cursor: 'pointer', fontSize: '0.75rem', fontWeight: 800,
            letterSpacing: '0.04em',
          }}>
            + NEW POLICY
          </button>
        </div>
      </div>

      {/* ── TAB NAV ───────────────────────────────────── */}
      <div style={{
        display: 'flex', gap: mobile ? '0.25rem' : '0.5rem', overflowX: 'auto',
        borderBottom: '1px solid var(--glass-border)',
        paddingBottom: 0,
      }}>
        {TABS.map(t => (
          <button key={t.id} onClick={() => setActiveTab(t.id)} style={{
            background: 'none', border: 'none', cursor: 'pointer',
            padding: mobile ? '0.6rem 0.7rem' : '0.7rem 1.1rem',
            fontSize: mobile ? '0.72rem' : '0.78rem', fontWeight: 600,
            color: activeTab === t.id ? 'var(--gold)' : 'var(--text-muted)',
            borderBottom: activeTab === t.id ? '2px solid var(--gold)' : '2px solid transparent',
            whiteSpace: 'nowrap', transition: 'all 0.2s',
          }}>
            <span style={{ marginRight: '0.35rem' }}>{t.icon}</span>{t.label}
          </button>
        ))}
      </div>

      {/* ── KPI ROW ───────────────────────────────────── */}
      {(activeTab === 'overview' || activeTab === 'analytics') && (
        <div style={{ display: 'grid', gridTemplateColumns: `repeat(auto-fit, minmax(${mobile ? '140px' : '160px'}, 1fr))`, gap: '1rem' }}>
          {[
            { label: 'TOTAL POLICIES', value: stats?.totalPolicies || 0, fmt: v => v },
            { label: 'ACTIVE POLICIES', value: stats?.activePolicies || 0, fmt: v => v, accent: '#22c55e' },
            { label: 'PREMIUM VOLUME', value: stats?.totalPremiumVolume || 0, fmt: fmtKES, accent: 'var(--gold)' },
            { label: 'EARNED COMMISSION', value: stats?.totalCommissionEarned || 0, fmt: fmtKES, accent: '#22c55e' },
            { label: 'PENDING PAYOUT', value: stats?.pendingCommission || 0, fmt: fmtKES, accent: '#f59e0b' },
            { label: 'CLIENTS', value: stats?.totalClients || 0, fmt: v => v },
          ].map((kpi, i) => (
            <div key={i} style={{ ...cardStyle, padding: mobile ? '1rem' : '1.25rem', textAlign: 'center' }}>
              <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '0.4rem' }}>{kpi.label}</div>
              <div style={{ fontSize: mobile ? '1.3rem' : '1.6rem', fontWeight: 800, color: kpi.accent || 'white' }}>{kpi.fmt(kpi.value)}</div>
            </div>
          ))}
        </div>
      )}

      {/* ═══════════════════════════════════════════════════
       *  TAB: OVERVIEW
       * ═══════════════════════════════════════════════════ */}
      {activeTab === 'overview' && (
        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '2fr 1fr', gap: '1.5rem' }}>
          {/* Recent policies */}
          <div style={{ ...cardStyle, padding: mobile ? '1.25rem' : '1.75rem' }}>
            <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.15rem', fontWeight: 700, margin: '0 0 1.25rem' }}>
              Recent Policies
            </h3>
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 520 }}>
                <thead>
                  <tr>{['CLIENT','POLICY','DEPARTURE','PREMIUM','STATUS'].map(h => <th key={h} style={thStyle}>{h}</th>)}</tr>
                </thead>
                <tbody>
                  {policies.slice(0, 6).map(p => {
                    const ps = getPS(p.policyStatus);
                    return (
                      <tr key={p.id} style={{ cursor: 'pointer' }} onClick={() => onNavigate?.('policy-detail', p.id)}>
                        <td style={tdStyle}>{p.insuredNames || '—'}</td>
                        <td style={tdStyle}><span style={{ color: 'var(--gold)', fontWeight: 600 }}>{p.policyNumber}</span></td>
                        <td style={tdStyle}>{fmtDate(p.departure)}</td>
                        <td style={{ ...tdStyle, fontWeight: 700 }}>{fmtKES(p.amountPaid)}</td>
                        <td style={tdStyle}><Badge {...ps} /></td>
                      </tr>
                    );
                  })}
                  {policies.length === 0 && (
                    <tr><td colSpan={5} style={{ ...tdStyle, textAlign: 'center', color: 'var(--text-muted)' }}>No policies yet</td></tr>
                  )}
                </tbody>
              </table>
            </div>
            {policies.length > 6 && (
              <button onClick={() => setActiveTab('policies')} style={{
                background: 'none', border: 'none', color: 'var(--gold)', cursor: 'pointer',
                fontSize: '0.78rem', fontWeight: 600, marginTop: '1rem', padding: 0,
              }}>View all {policies.length} policies →</button>
            )}
          </div>

          {/* Right: Quick Glance */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            {/* Conversion */}
            <div style={{ ...cardStyle, padding: '1.25rem', textAlign: 'center' }}>
              <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '0.5rem' }}>CONVERSION RATE</div>
              <div style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--gold)' }}>{stats?.conversionRate || 0}%</div>
              <div style={{ color: 'var(--text-muted)', fontSize: '0.7rem', marginTop: '0.3rem' }}>Active / Total</div>
            </div>

            {/* Status donut */}
            <div style={{ ...cardStyle, padding: '1.25rem' }}>
              <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '0.8rem', textAlign: 'center' }}>STATUS BREAKDOWN</div>
              <div style={{ display: 'flex', justifyContent: 'center' }}>
                <DonutChart data={statusDist} size={140} />
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginTop: '0.8rem', justifyContent: 'center' }}>
                {statusDist.map((d, i) => (
                  <span key={i} style={{ display: 'flex', alignItems: 'center', gap: '0.3rem', fontSize: '0.68rem', color: 'var(--text-muted)' }}>
                    <span style={{ width: 8, height: 8, borderRadius: '50%', background: DONUT_COLORS[i % DONUT_COLORS.length], display: 'inline-block' }} />
                    {d.status} ({d.count})
                  </span>
                ))}
              </div>
            </div>

            {/* Top products */}
            <div style={{ ...cardStyle, padding: '1.25rem' }}>
              <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '0.8rem' }}>TOP PRODUCTS</div>
              {topProducts.map((tp, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.5rem 0', borderBottom: i < topProducts.length - 1 ? '1px solid rgba(255,255,255,0.04)' : 'none' }}>
                  <div>
                    <div style={{ fontSize: '0.82rem', fontWeight: 600 }}>{tp.policyTitle || 'Unknown'}</div>
                    <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)' }}>{tp.soldCount} sold</div>
                  </div>
                  <div style={{ fontSize: '0.78rem', fontWeight: 700, color: 'var(--gold)' }}>{fmtKES(tp.totalPremium)}</div>
                </div>
              ))}
              {topProducts.length === 0 && <div style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>No data yet</div>}
            </div>
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════
       *  TAB: CLIENTS CRM
       * ═══════════════════════════════════════════════════ */}
      {activeTab === 'clients' && (
        <div style={{ ...cardStyle, padding: mobile ? '1.25rem' : '1.75rem' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', marginBottom: '1.25rem' }}>
            <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.15rem', fontWeight: 700, margin: 0 }}>
              Client Directory <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', fontWeight: 400 }}>({filteredClients.length})</span>
            </h3>
            <input
              placeholder="Search name, email, phone…"
              value={clientSearch} onChange={e => setClientSearch(e.target.value)}
              style={{ ...inputStyle, maxWidth: 260 }}
            />
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 650 }}>
              <thead>
                <tr>{['CLIENT','EMAIL','PHONE','POLICIES','TOTAL PREMIUM','LAST ACTIVE','ACTIONS'].map(h => <th key={h} style={thStyle}>{h}</th>)}</tr>
              </thead>
              <tbody>
                {filteredClients.map(c => {
                  const signalled = clientActions[c.email] === 'signalled';
                  return (
                    <tr key={c.email} style={{ background: signalled ? 'rgba(245,158,11,0.06)' : 'transparent' }}>
                      <td style={tdStyle}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <div style={{
                            width: 30, height: 30, borderRadius: '50%',
                            background: 'linear-gradient(135deg, var(--gold), #b8941f)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '0.7rem', fontWeight: 800, color: '#0f172a', flexShrink: 0,
                          }}>
                            {(c.name || '?')[0]?.toUpperCase()}
                          </div>
                          <span style={{ fontWeight: 600 }}>{c.name || '—'}</span>
                          {c.hasActive && <span style={{ width: 6, height: 6, borderRadius: '50%', background: '#22c55e', flexShrink: 0 }} title="Has active policy" />}
                          {signalled && <span style={{ fontSize: '0.7rem', color: '#f59e0b', fontWeight: 700 }}>⚑</span>}
                        </div>
                      </td>
                      <td style={{ ...tdStyle, fontSize: '0.78rem' }}>{c.email}</td>
                      <td style={tdStyle}>{c.phone || '—'}</td>
                      <td style={{ ...tdStyle, textAlign: 'center', fontWeight: 700 }}>{c.policiesCount}</td>
                      <td style={{ ...tdStyle, fontWeight: 700 }}>{fmtKES(c.totalPremium)}</td>
                      <td style={{ ...tdStyle, fontSize: '0.75rem', color: 'var(--text-muted)' }}>{fmtDate(c.lastActivity)}</td>
                      <td style={tdStyle}>
                        <div style={{ display: 'flex', gap: '0.3rem' }}>
                          <button title="Contact via email"
                            onClick={() => window.open(`mailto:${c.email}`, '_blank')}
                            style={{ background: 'rgba(59,130,246,0.12)', border: 'none', borderRadius: '6px', padding: '0.3rem 0.5rem', cursor: 'pointer', color: '#60a5fa', fontSize: '0.7rem', fontWeight: 700 }}>
                            ✉ Contact
                          </button>
                          <button title="Flag for follow-up"
                            onClick={() => setClientActions(prev => ({ ...prev, [c.email]: prev[c.email] === 'signalled' ? undefined : 'signalled' }))}
                            style={{ background: signalled ? 'rgba(245,158,11,0.2)' : 'rgba(245,158,11,0.08)', border: 'none', borderRadius: '6px', padding: '0.3rem 0.5rem', cursor: 'pointer', color: '#f59e0b', fontSize: '0.7rem', fontWeight: 700 }}>
                            ⚑ {signalled ? 'Unflag' : 'Signal'}
                          </button>
                          <button title="Archive client"
                            onClick={() => {
                              if (window.confirm(`Archive ${c.name || c.email}? They'll be hidden from this list.`)) {
                                setClientActions(prev => ({ ...prev, [c.email]: 'archived' }));
                              }
                            }}
                            style={{ background: 'rgba(248,113,113,0.08)', border: 'none', borderRadius: '6px', padding: '0.3rem 0.5rem', cursor: 'pointer', color: '#f87171', fontSize: '0.7rem', fontWeight: 700 }}>
                            ✕ Archive
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
                {filteredClients.length === 0 && (
                  <tr><td colSpan={7} style={{ ...tdStyle, textAlign: 'center', color: 'var(--text-muted)', padding: '2rem' }}>
                    {clientSearch ? 'No clients match your search' : 'No clients yet — start selling!'}
                  </td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════
       *  TAB: POLICIES
       * ═══════════════════════════════════════════════════ */}
      {activeTab === 'policies' && (
        <div style={{ ...cardStyle, padding: mobile ? '1.25rem' : '1.75rem' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', marginBottom: '1.25rem' }}>
            <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.15rem', fontWeight: 700, margin: 0 }}>
              All Policies <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem', fontWeight: 400 }}>({filteredPolicies.length})</span>
            </h3>
            <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
              {[{ id: 'all', label: 'All' }, ...Object.entries(POLICY_STATUS).map(([id, v]) => ({ id, label: v.label }))].map(f => (
                <button key={f.id} onClick={() => setPolicyFilter(f.id)} style={{
                  background: policyFilter === f.id ? 'var(--gold)' : 'rgba(255,255,255,0.06)',
                  color: policyFilter === f.id ? '#0f172a' : 'var(--text-muted)',
                  border: 'none', borderRadius: '6px', padding: '0.35rem 0.7rem',
                  fontSize: '0.68rem', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap',
                }}>
                  {f.label}
                </button>
              ))}
            </div>
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 780 }}>
              <thead>
                <tr>{['POLICY #','CLIENT','PRODUCT','DEPARTURE','RETURN','PREMIUM','PAYMENT','STATUS','ACTIONS'].map(h => <th key={h} style={thStyle}>{h}</th>)}</tr>
              </thead>
              <tbody>
                {filteredPolicies.map(p => {
                  const ps = getPS(p.policyStatus);
                  const pay = getPay(p.paymentStatus);
                  const isSelf = authUser?.email && p.insuredEmail?.toLowerCase() === authUser.email.toLowerCase();
                  const isUnpaid = p.paymentStatus !== 'confirmed';
                  return (
                    <tr key={p.id} style={{ cursor: 'pointer', background: isSelf ? 'rgba(245,158,11,0.04)' : 'transparent' }} onClick={() => onNavigate?.('policy-detail', p.id)}>
                      <td style={tdStyle}><span style={{ color: 'var(--gold)', fontWeight: 600, fontSize: '0.78rem' }}>{p.policyNumber}</span></td>
                      <td style={tdStyle}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                          {p.insuredNames || '—'}
                          {isSelf && <span style={{
                            display: 'inline-block', padding: '0.12rem 0.45rem', borderRadius: '5px',
                            fontSize: '0.6rem', fontWeight: 800, letterSpacing: '0.04em',
                            color: '#f59e0b', background: 'rgba(245,158,11,0.15)', whiteSpace: 'nowrap',
                          }}>⚑ SELF</span>}
                        </div>
                      </td>
                      <td style={{ ...tdStyle, fontSize: '0.78rem' }}>{p.policyTitle || '—'}</td>
                      <td style={tdStyle}>{fmtDate(p.departure)}</td>
                      <td style={tdStyle}>{fmtDate(p.returnDate)}</td>
                      <td style={{ ...tdStyle, fontWeight: 700 }}>{fmtKES(p.amountPaid)}</td>
                      <td style={tdStyle}><span style={{ color: pay.color, fontWeight: 700, fontSize: '0.75rem' }}>{pay.label}</span></td>
                      <td style={tdStyle}><Badge {...ps} /></td>
                      <td style={tdStyle}>
                        <div style={{ display: 'flex', gap: '0.3rem' }} onClick={e => e.stopPropagation()}>
                          {isUnpaid && (
                            <button title="Edit policy details" onClick={() => setEditPolicy(p)} style={{
                              background: 'rgba(59,130,246,0.12)', border: 'none', borderRadius: '6px',
                              padding: '0.3rem 0.55rem', cursor: 'pointer', color: '#60a5fa',
                              fontSize: '0.68rem', fontWeight: 700, whiteSpace: 'nowrap',
                            }}>✎ Edit</button>
                          )}
                          {isSelf && isUnpaid && (
                            <button title="Assign to a client" onClick={() => setAssignPolicy(p)} style={{
                              background: 'rgba(34,197,94,0.12)', border: 'none', borderRadius: '6px',
                              padding: '0.3rem 0.55rem', cursor: 'pointer', color: '#22c55e',
                              fontSize: '0.68rem', fontWeight: 700, whiteSpace: 'nowrap',
                            }}>→ Assign</button>
                          )}
                          {!isUnpaid && !isSelf && (
                            <span style={{ color: 'var(--text-muted)', fontSize: '0.65rem' }}>—</span>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
                {filteredPolicies.length === 0 && (
                  <tr><td colSpan={9} style={{ ...tdStyle, textAlign: 'center', color: 'var(--text-muted)', padding: '2rem' }}>No policies found</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════
       *  TAB: COMMISSIONS
       * ═══════════════════════════════════════════════════ */}
      {activeTab === 'commissions' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Commission summary cards */}
          <div style={{ display: 'grid', gridTemplateColumns: `repeat(auto-fit, minmax(${mobile ? '140px' : '180px'}, 1fr))`, gap: '1rem' }}>
            {[
              { label: 'TOTAL EARNED', value: fmtKES(stats?.totalCommissionEarned || 0), color: '#22c55e' },
              { label: 'PENDING PAYOUT', value: fmtKES(stats?.pendingCommission || 0), color: '#f59e0b' },
              { label: 'DISPUTED', value: fmtKES(stats?.disputedCommission || 0), color: '#f87171' },
            ].map((c, i) => (
              <div key={i} style={{ ...cardStyle, padding: '1.25rem', textAlign: 'center' }}>
                <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '0.4rem' }}>{c.label}</div>
                <div style={{ fontSize: '1.4rem', fontWeight: 800, color: c.color }}>{c.value}</div>
              </div>
            ))}
          </div>

          {/* Commission table */}
          <div style={{ ...cardStyle, padding: mobile ? '1.25rem' : '1.75rem' }}>
            <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', marginBottom: '1.25rem' }}>
              <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.15rem', fontWeight: 700, margin: 0 }}>Commission Ledger</h3>
              <div style={{ display: 'flex', gap: '0.4rem' }}>
                {[{ id: 'all', label: 'All' }, { id: 'unpaid', label: 'Unpaid' }, { id: 'paid', label: 'Paid' }, { id: 'received', label: 'Received' }, { id: 'disputed', label: 'Disputed' }].map(f => (
                  <button key={f.id} onClick={() => setCommFilter(f.id)} style={{
                    background: commFilter === f.id ? 'var(--gold)' : 'rgba(255,255,255,0.06)',
                    color: commFilter === f.id ? '#0f172a' : 'var(--text-muted)',
                    border: 'none', borderRadius: '6px', padding: '0.35rem 0.7rem',
                    fontSize: '0.68rem', fontWeight: 700, cursor: 'pointer',
                  }}>
                    {f.label}
                  </button>
                ))}
              </div>
            </div>
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 600 }}>
                <thead>
                  <tr>{['POLICY #','CLIENT','PREMIUM','COMMISSION','STATUS','DATE'].map(h => <th key={h} style={thStyle}>{h}</th>)}</tr>
                </thead>
                <tbody>
                  {filteredCommissions.map(p => {
                    const cm = getComm(p.agentCommissionStatus);
                    return (
                      <tr key={p.id}>
                        <td style={tdStyle}><span style={{ color: 'var(--gold)', fontWeight: 600, fontSize: '0.78rem' }}>{p.policyNumber}</span></td>
                        <td style={tdStyle}>{p.insuredNames || '—'}</td>
                        <td style={{ ...tdStyle, fontWeight: 700 }}>{fmtKES(p.amountPaid)}</td>
                        <td style={{ ...tdStyle, fontWeight: 800, fontSize: '0.9rem' }}>{fmtKES(p.agentCommissionAmount)}</td>
                        <td style={tdStyle}><Badge label={cm.label} color={cm.color} bg={cm.bg} /></td>
                        <td style={{ ...tdStyle, color: 'var(--text-muted)', fontSize: '0.78rem' }}>{fmtDate(p.createdAt)}</td>
                      </tr>
                    );
                  })}
                  {filteredCommissions.length === 0 && (
                    <tr><td colSpan={6} style={{ ...tdStyle, textAlign: 'center', color: 'var(--text-muted)', padding: '2rem' }}>No commission records</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════
       *  TAB: ANALYTICS
       * ═══════════════════════════════════════════════════ */}
      {activeTab === 'analytics' && (
        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: '1.5rem' }}>
          {/* Revenue chart */}
          <div style={{ ...cardStyle, padding: '1.5rem' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '1rem' }}>MONTHLY PREMIUM VOLUME</div>
            {monthly.length > 0 ? (
              <BarChart data={monthly} dataKey="premium" color="var(--gold, #d4af37)" />
            ) : (
              <div style={{ color: 'var(--text-muted)', fontSize: '0.82rem', textAlign: 'center', padding: '2rem 0' }}>No data yet</div>
            )}
          </div>

          {/* Commission trend */}
          <div style={{ ...cardStyle, padding: '1.5rem' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '1rem' }}>MONTHLY COMMISSION</div>
            {monthly.length > 0 ? (
              <BarChart data={monthly} dataKey="commission" color="#22c55e" />
            ) : (
              <div style={{ color: 'var(--text-muted)', fontSize: '0.82rem', textAlign: 'center', padding: '2rem 0' }}>No data yet</div>
            )}
          </div>

          {/* New clients per month */}
          <div style={{ ...cardStyle, padding: '1.5rem' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '1rem' }}>NEW CLIENTS PER MONTH</div>
            {monthly.length > 0 ? (
              <BarChart data={monthly} dataKey="clients" color="#a78bfa" />
            ) : (
              <div style={{ color: 'var(--text-muted)', fontSize: '0.82rem', textAlign: 'center', padding: '2rem 0' }}>No data yet</div>
            )}
          </div>

          {/* Policies per month */}
          <div style={{ ...cardStyle, padding: '1.5rem' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '1rem' }}>POLICIES PER MONTH</div>
            {monthly.length > 0 ? (
              <BarChart data={monthly} dataKey="policies" color="#06b6d4" />
            ) : (
              <div style={{ color: 'var(--text-muted)', fontSize: '0.82rem', textAlign: 'center', padding: '2rem 0' }}>No data yet</div>
            )}
          </div>

          {/* Status distribution (full width) */}
          <div style={{ ...cardStyle, padding: '1.5rem', gridColumn: mobile ? '1' : '1 / -1' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '1rem' }}>POLICY STATUS DISTRIBUTION</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: '2rem' }}>
              <DonutChart data={statusDist} size={180} />
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                {statusDist.map((d, i) => {
                  const ps = getPS(d.status);
                  return (
                    <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <span style={{ width: 10, height: 10, borderRadius: '3px', background: DONUT_COLORS[i % DONUT_COLORS.length], flexShrink: 0 }} />
                      <span style={{ fontSize: '0.8rem', minWidth: 80 }}>{ps.label}</span>
                      <span style={{ fontSize: '0.8rem', fontWeight: 700 }}>{d.count}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Top products full-width */}
          <div style={{ ...cardStyle, padding: '1.5rem', gridColumn: mobile ? '1' : '1 / -1' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.1em', marginBottom: '1rem' }}>TOP SELLING PRODUCTS</div>
            <div style={{ display: 'grid', gridTemplateColumns: `repeat(auto-fit, minmax(${mobile ? '200px' : '220px'}, 1fr))`, gap: '1rem' }}>
              {topProducts.map((tp, i) => (
                <div key={i} style={{ ...cardStyle, padding: '1rem', display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
                  <div style={{ fontSize: '0.9rem', fontWeight: 700 }}>{tp.policyTitle || 'Unknown'}</div>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <span style={{ color: 'var(--text-muted)', fontSize: '0.75rem' }}>{tp.soldCount} sold</span>
                    <span style={{ color: 'var(--gold)', fontWeight: 700, fontSize: '0.82rem' }}>{fmtKES(tp.totalPremium)}</span>
                  </div>
                  {/* Mini progress bar */}
                  <div style={{ height: 4, borderRadius: 2, background: 'rgba(255,255,255,0.08)', marginTop: '0.3rem' }}>
                    <div style={{
                      height: '100%', borderRadius: 2, background: DONUT_COLORS[i % DONUT_COLORS.length],
                      width: `${Math.min(100, (tp.soldCount / Math.max(...topProducts.map(t => t.soldCount), 1)) * 100)}%`,
                    }} />
                  </div>
                </div>
              ))}
              {topProducts.length === 0 && <div style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>No data yet</div>}
            </div>
          </div>
        </div>
      )}

      {/* ── MODALS ──────────────────────────────────── */}
      {showProfileEdit && <ProfileEditModal onClose={() => setShowProfileEdit(false)} />}
      {editPolicy && (
        <PolicyEditModal
          policy={editPolicy}
          onClose={() => setEditPolicy(null)}
          onSaved={() => { setEditPolicy(null); polResult.reexecute?.({ requestPolicy: 'network-only' }); }}
        />
      )}
      {assignPolicy && (
        <AssignClientModal
          policy={assignPolicy}
          clients={clients}
          onClose={() => setAssignPolicy(null)}
          onAssigned={() => { setAssignPolicy(null); polResult.reexecute?.({ requestPolicy: 'network-only' }); dashResult.reexecute?.({ requestPolicy: 'network-only' }); }}
        />
      )}
    </div>
  );
};

export default AgentDashboard;
