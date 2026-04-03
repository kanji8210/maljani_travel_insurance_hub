import React, { useState } from 'react';
import { useAuth } from '../lib/AuthContext';

const Login = ({ onNavigate }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const { login, error, loading } = useAuth();
  const [localError, setLocalError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLocalError('');
    if (!email || !password) {
      setLocalError('Please fill in all fields.');
      return;
    }

    const result = await login(email, password);
    if (result.success) {
      if (result.role === 'insured' || result.role === 'client') {
        onNavigate('dashboard');
      } else {
        onNavigate('landing');
      }
    }
  };

  return (
    <div className="fade-in" style={{ 
      display: 'flex', 
      justifyContent: 'center', 
      alignItems: 'center', 
      minHeight: '100vh',
      padding: '120px 16px 60px',
    }}>
      <div className="glass-card" style={{ width: '100%', maxWidth: '450px' }}>
        <h2 className="serif" style={{ fontSize: '2rem', marginBottom: '0.5rem', textAlign: 'center' }}>
          Welcome <span className="gold-text">Back</span>
        </h2>
        <p style={{ color: 'var(--text-muted)', textAlign: 'center', marginBottom: '2.5rem', fontSize: '0.9rem' }}>
          Secure access to your Maljani Hub account.
        </p>

        {(error || localError) && (
          <div style={{ 
            background: 'rgba(139, 0, 0, 0.2)', 
            border: '1px solid var(--crimson)', 
            color: 'white', 
            padding: '1rem', 
            borderRadius: '8px', 
            marginBottom: '1.5rem',
            fontSize: '0.85rem'
          }}>
            {error || localError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div style={{ marginBottom: '1.5rem' }}>
            <label style={{ display: 'block', fontSize: '0.8rem', color: 'var(--gold)', marginBottom: '0.5rem', letterSpacing: '0.1em' }}>
              EMAIL ADDRESS
            </label>
            <input 
              type="email" 
              className="input-field" 
              placeholder="name@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div style={{ marginBottom: '2rem' }}>
            <label style={{ display: 'block', fontSize: '0.8rem', color: 'var(--gold)', marginBottom: '0.5rem', letterSpacing: '0.1em' }}>
              PASSWORD
            </label>
            <input 
              type="password" 
              className="input-field" 
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>

          <button 
            type="submit" 
            className="btn-luxury" 
            style={{ width: '100%', marginBottom: '1.5rem' }}
            disabled={loading}
          >
            {loading ? 'AUTHENTICATING...' : 'SECURE LOGIN'}
          </button>

          <div style={{ textAlign: 'center', fontSize: '0.85rem', color: 'var(--text-muted)' }}>
            Don't have an account?{' '}
            <span 
              style={{ color: 'var(--gold)', cursor: 'pointer', fontWeight: '600' }}
              onClick={() => onNavigate('register')}
            >
              Request Access
            </span>
          </div>
        </form>
      </div>
    </div>
  );
};

export default Login;
