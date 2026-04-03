import React from 'react';

const AgentDashboard = ({ user, onNavigate }) => {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
      {/* KPI Stats Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1.5rem' }}>
        <div className="glass-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.75rem', fontWeight: '700', letterSpacing: '0.1em', marginBottom: '0.5rem' }}>TOTAL SALES</div>
            <div style={{ fontSize: '2rem', fontWeight: '800', color: 'var(--gold)' }}>$42,850</div>
            <div style={{ fontSize: '0.7rem', color: '#4ade80', marginTop: '0.3rem' }}>↑ 12% vs last month</div>
        </div>
        <div className="glass-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.75rem', fontWeight: '700', letterSpacing: '0.1em', marginBottom: '0.5rem' }}>ACTIVE CLIENTS</div>
            <div style={{ fontSize: '2rem', fontWeight: '800' }}>156</div>
            <div style={{ fontSize: '0.7rem', color: '#4ade80', marginTop: '0.3rem' }}>+5 new this week</div>
        </div>
        <div className="glass-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.75rem', fontWeight: '700', letterSpacing: '0.1em', marginBottom: '0.5rem' }}>PENDING PAYOUT</div>
            <div style={{ fontSize: '2rem', fontWeight: '800', color: 'white' }}>$1,240</div>
            <div style={{ fontSize: '0.7rem', color: 'var(--gold)', marginTop: '0.3rem' }}>Next payout: Oct 25</div>
        </div>
        <div className="glass-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.75rem', fontWeight: '700', letterSpacing: '0.1em', marginBottom: '0.5rem' }}>AGENCY RANK</div>
            <div style={{ fontSize: '2rem', fontWeight: '800', color: '#FBF5B7' }}>GOLD</div>
            <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: '0.3rem' }}>Elite status at $50k</div>
        </div>
      </div>

      {/* Main Content Area */}
      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '2rem' }}>
        {/* Left: Recent Sales / Policy Hub */}
        <section className="glass-card" style={{ padding: '2rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2rem' }}>
            <h3 className="serif" style={{ fontSize: '1.5rem' }}>Recent Policy Hub</h3>
            <button className="btn-luxury" style={{ padding: '0.6rem 1.2rem', fontSize: '0.7rem' }}>NEW SALE</button>
          </div>
          
          <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
            <thead>
              <tr style={{ color: 'var(--gold)', fontSize: '0.8rem', borderBottom: '1px solid var(--glass-border)' }}>
                <th style={{ padding: '1rem 0' }}>CLIENT</th>
                <th style={{ padding: '1rem 0' }}>POLICY</th>
                <th style={{ padding: '1rem 0' }}>DATE</th>
                <th style={{ padding: '1rem 0' }}>PREMIUM</th>
                <th style={{ padding: '1rem 0' }}>STATUS</th>
              </tr>
            </thead>
            <tbody style={{ fontSize: '0.9rem' }}>
              <tr style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                <td style={{ padding: '1.2rem 0' }}>Alexander Pierce</td>
                <td>Global Elite</td>
                <td>Oct 12, 2024</td>
                <td style={{ fontWeight: '700' }}>$450.00</td>
                <td><span style={{ color: '#4ade80', background: 'rgba(74, 222, 128, 0.1)', padding: '0.3rem 0.6rem', borderRadius: '4px', fontSize: '0.75rem' }}>ISSUED</span></td>
              </tr>
              <tr style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                <td style={{ padding: '1.2rem 0' }}>Sarah Jenkins</td>
                <td>Schengen Gold</td>
                <td>Oct 11, 2024</td>
                <td style={{ fontWeight: '700' }}>$120.00</td>
                <td><span style={{ color: '#4ade80', background: 'rgba(74, 222, 128, 0.1)', padding: '0.3rem 0.6rem', borderRadius: '4px', fontSize: '0.75rem' }}>ISSUED</span></td>
              </tr>
              <tr style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                <td style={{ padding: '1.2rem 0' }}>Marco Rossi</td>
                <td>Student Prem</td>
                <td>Oct 10, 2024</td>
                <td style={{ fontWeight: '700' }}>$850.00</td>
                <td><span style={{ color: 'var(--gold)', background: 'rgba(212, 175, 55, 0.1)', padding: '0.3rem 0.6rem', borderRadius: '4px', fontSize: '0.75rem' }}>PENDING</span></td>
              </tr>
            </tbody>
          </table>
        </section>

        {/* Right: Agency Alerts / Notices */}
        <aside className="glass-card" style={{ padding: '2rem' }}>
            <h3 className="serif" style={{ fontSize: '1.3rem', marginBottom: '1.5rem' }}>Agency Alerts</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                <div style={{ borderLeft: '3px solid var(--gold)', paddingLeft: '1rem' }}>
                    <div style={{ fontSize: '0.8rem', fontWeight: '700', marginBottom: '0.2rem' }}>COMMISSION UPDATE</div>
                    <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Global Elite commission rates increased by 2% for Q4.</p>
                </div>
                <div style={{ borderLeft: '3px solid var(--crimson)', paddingLeft: '1rem' }}>
                    <div style={{ fontSize: '0.8rem', fontWeight: '700', marginBottom: '0.2rem' }}>URGENT: KYB INFO</div>
                    <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Please update your tax documents by Oct 20 to avoid payout delays.</p>
                </div>
                <div style={{ borderLeft: '3px solid var(--forest)', paddingLeft: '1rem' }}>
                    <div style={{ fontSize: '0.8rem', fontWeight: '700', marginBottom: '0.2rem' }}>NEW POLICY LAUNCHED</div>
                    <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>'Titanium Business' now available for corporate groups.</p>
                </div>
            </div>
            <button className="btn-luxury gold-border" style={{ marginTop: '2rem', width: '100%', background: 'transparent', color: 'white', fontSize: '0.75rem' }}>VIEW ALL NOTICES</button>
        </aside>
      </div>
    </div>
  );
};

export default AgentDashboard;
