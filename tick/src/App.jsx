import React from 'react'
import { Provider } from 'urql'
import { client } from './lib/graphql'
import { AuthProvider } from './lib/AuthContext'
import LandingPage from './components/LandingPage'
import QuoteWizard from './components/QuoteWizard'
import Header from './components/Header'
import Footer from './components/Footer'
import Login from './components/Login'
import Register from './components/Register'
import Dashboard from './components/Dashboard'
import PolicyDetail from './components/PolicyDetail'
import Catalog from './components/Catalog'
import VerifyPolicy from './components/VerifyPolicy'
import AboutPage from './components/AboutPage'
import AgenciesPage from './components/AgenciesPage'

function AppContent() {
  const STORAGE_KEY = 'maljani_app_navigation';

  const loadStoredNavigation = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return { history: [{ view: 'landing', policyId: null, searchData: null, forceStep: null }], idx: 0 };
      const parsed = JSON.parse(raw);
      if (!parsed || !Array.isArray(parsed.history) || typeof parsed.idx !== 'number') {
        return { history: [{ view: 'landing', policyId: null, searchData: null, forceStep: null }], idx: 0 };
      }
      const idx = Math.min(Math.max(parsed.idx, 0), parsed.history.length - 1);
      return { history: parsed.history, idx };
    } catch {
      return { history: [{ view: 'landing', policyId: null, searchData: null, forceStep: null }], idx: 0 };
    }
  };

  const stored = loadStoredNavigation();
  const [history, setHistory] = React.useState(stored.history);
  const [historyIdx, setHistoryIdx] = React.useState(stored.idx);

  const activeView    = history[historyIdx].view;
  const selectedPolicyId = history[historyIdx].policyId;
  const canGoBack     = historyIdx > 0;
  const canGoForward  = historyIdx < history.length - 1;

  const persistNavigation = (historyArray, idx) => {
    try {
      localStorage.setItem('maljani_app_navigation', JSON.stringify({ history: historyArray, idx }));
    } catch {
      // ignore localStorage failures
    }
  };

  const handleNavigate = (view, policyId = null, searchData = null, forceStep = null) => {
    const entry = { view, policyId, searchData, forceStep };
    const nextHistory = [...history.slice(0, historyIdx + 1), entry];
    const nextIdx = historyIdx + 1;

    setHistory(nextHistory);
    setHistoryIdx(nextIdx);
    persistNavigation(nextHistory, nextIdx);
    window.scrollTo(0, 0);
  };

  const handleBack = () => {
    if (!canGoBack) return;
    const nextIdx = historyIdx - 1;
    setHistoryIdx(nextIdx);
    persistNavigation(history, nextIdx);
    window.scrollTo(0, 0);
  };

  const handleForward = () => {
    if (!canGoForward) return;
    const nextIdx = historyIdx + 1;
    setHistoryIdx(nextIdx);
    persistNavigation(history, nextIdx);
    window.scrollTo(0, 0);
  };

  const renderView = () => {
    switch(activeView) {
      case 'dashboard':
        return <Dashboard onNavigate={handleNavigate} />;
      case 'wizard':
        return (
          <div className="fade-in" style={{ padding: '4rem 0', maxWidth: '800px', margin: '0 auto' }}>
            <QuoteWizard
              initialPolicyId={history[historyIdx].policyId}
              initialSearchData={history[historyIdx].searchData}
              initialStep={history[historyIdx].forceStep}
              onNavigate={handleNavigate}
            />
            <button
              className="btn-luxury"
              style={{
                marginTop: '2rem',
                background: 'transparent',
                border: '1px solid var(--glass-border)',
                color: 'white',
                width: '100%'
              }}
              onClick={() => handleNavigate('landing')}
            >
              Back to Home
            </button>
          </div>
        );
      case 'login':
        return <Login onNavigate={handleNavigate} />;
      case 'register':
        return <Register onNavigate={handleNavigate} />;
      case 'catalog':
        return <Catalog onNavigate={handleNavigate} />;
      case 'verify':
        return <VerifyPolicy onNavigate={handleNavigate} />;
      case 'about':
        return <AboutPage onNavigate={handleNavigate} />;
      case 'agencies':
        return <AgenciesPage onNavigate={handleNavigate} />;
      case 'policy-detail':
        return (
          <PolicyDetail 
            policyId={selectedPolicyId} 
            searchData={history[historyIdx].searchData}
            onBack={() => handleNavigate('landing')} 
            onStartWizard={(id, data, step) => handleNavigate('wizard', id, data, step)}
          />
        );
      case 'landing':
      default:
        return (
          <LandingPage 
            onStartWizard={(id, data, step) => handleNavigate('wizard', id ?? null, data ?? null, step ?? null)} 
            onNavigate={handleNavigate} 
          />
        );
    }
  }

  return (
    <div className="app-container">
      <div className="bg-mesh" aria-hidden="true" />
      <div className="bg-grid" aria-hidden="true" />
      <Header
        onNavigate={handleNavigate}
        activeView={activeView}
        canGoBack={canGoBack}
        canGoForward={canGoForward}
        onBack={handleBack}
        onForward={handleForward}
      />
      <main style={{ position: 'relative', zIndex: 1 }}>
        {renderView()}
      </main>
      <Footer onNavigate={handleNavigate} />
    </div>
  )
}

function App() {
  return (
    <Provider value={client}>
      <AuthProvider>
        <AppContent />
      </AuthProvider>
    </Provider>
  )
}

export default App
