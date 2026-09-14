import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { HomePage } from '../pages/HomePage';
import { FamilyManagementPage } from '../features/family/FamilyManagementPage';
import { AuthProvider } from '../features/auth/AuthContext';
import { AuthGate } from '../features/auth/AuthGate';
import { ChildQrLoginPage } from '../features/auth/ChildQrLoginPage';

const basename = import.meta.env.BASE_URL.replace(/\/$/, '');

export function App() {
  return (
    <BrowserRouter basename={basename}>
      <AuthProvider>
        <Routes>
          {/* Ausserhalb des AuthGate: ein Kind-Tablet ist beim Oeffnen dieses
              Links noch nicht angemeldet, der QR-Scan MUSS also ohne
              vorherigen Login erreichbar sein. */}
          <Route path="/kind/:token" element={<ChildQrLoginPage />} />
          <Route
            path="/*"
            element={
              <AuthGate>
                <Routes>
                  <Route path="/" element={<HomePage />} />
                  <Route path="/familie" element={<FamilyManagementPage />} />
                </Routes>
              </AuthGate>
            }
          />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
