import React from 'react';
import PolicyShowcase from './PolicyShowcase';

const Catalog = ({ onNavigate }) => {
  return (
    <div className="fade-in" style={{ padding: '6rem 0' }}>
      <div style={{ textAlign: 'center', marginBottom: '4rem' }}>
        <span style={{ color: 'var(--gold)', letterSpacing: '0.4em', fontSize: '0.8rem', fontWeight: '800' }}>ELITE COVERAGE CATALOG</span>
        <h1 className="serif" style={{ fontSize: '3.5rem', marginTop: '1rem' }}>Our Global <span className="gold-text">Underwriting.</span></h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '1.1rem', marginTop: '1rem', maxWidth: '700px', margin: '0 auto' }}>
          Browse our curated selection of travel insurance plans, designed for the discerning global citizen.
        </p>
      </div>

      <PolicyShowcase onNavigate={onNavigate} />

      <div style={{ textAlign: 'center', marginTop: '6rem', padding: '4rem', background: 'rgba(255,255,255,0.02)', borderRadius: '24px' }}>
        <h3 className="serif" style={{ fontSize: '2rem' }}>Need a <span className="gold-text">Custom Quote?</span></h3>
        <p style={{ color: 'var(--text-muted)', marginBottom: '2rem' }}>Input your exact travel dates for precision-engineered pricing.</p>
        <button className="btn-luxury" onClick={() => onNavigate('wizard')}>START SEARCH WIZARD</button>
      </div>
    </div>
  );
};

export default Catalog;
