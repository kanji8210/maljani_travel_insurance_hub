import React from 'react';
import { useAuth } from '../lib/AuthContext';
import InsuredDashboard from './InsuredDashboard';
import AgentDashboard from './AgentDashboard';

const Dashboard = ({ onNavigate }) => {
  const { user, role } = useAuth();

  const renderDashboard = () => {
    switch (role) {
      case 'agent':
      case 'administrator': // Admins can see the agent dashboard for now
        return <AgentDashboard user={user} onNavigate={onNavigate} />;
      case 'insured':
        return <InsuredDashboard user={user} onNavigate={onNavigate} />;
      default:
        return (
          <div className="glass-card" style={{ textAlign: 'center', margin: '4rem auto', maxWidth: '600px' }}>
            <h3 className="serif" style={{ color: 'var(--gold)', marginBottom: '1rem' }}>Access Restricted</h3>
            <p style={{ color: 'var(--text-muted)' }}>You do not have permission to view this dashboard. Please log in with a valid account.</p>
            <button className="btn-luxury" style={{ marginTop: '2rem' }} onClick={() => onNavigate('login')}>GO TO LOGIN</button>
          </div>
        );
    }
  };

  return (
    <div className="fade-in container" style={{ paddingTop: 110, paddingBottom: 80 }}>
      <header style={{ marginBottom: 36, display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
        <div>
          <p className="section-label">{role === 'insured' ? 'My Account' : role === 'agent' ? 'Agency Panel' : 'Admin Panel'}</p>
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(26px,3.5vw,42px)', fontWeight: 800, lineHeight: 1.1, marginTop: 6 }}>
            Welcome back, <span style={{ color: 'var(--gold)' }}>{user?.name || 'User'}</span>
          </h1>
        </div>
      </header>
      
      {renderDashboard()}
    </div>
  );
};

export default Dashboard;
