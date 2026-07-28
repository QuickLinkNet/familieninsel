import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { HomePage } from '../pages/HomePage';
import { AuthProvider } from '../features/auth/AuthContext';
import { AuthGate } from '../features/auth/AuthGate';

const basename = import.meta.env.BASE_URL.replace(/\/$/, '');

export function App() {
  return (
    <BrowserRouter basename={basename}>
      <AuthProvider>
        <AuthGate>
          <Routes>
            <Route path="/" element={<HomePage />} />
          </Routes>
        </AuthGate>
      </AuthProvider>
    </BrowserRouter>
  );
}
