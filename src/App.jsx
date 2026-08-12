// App.jsx
import { Routes, Route } from 'react-router-dom';
import Login from './pages/login';
import Dashboard from './pages/dashboard';
import LPDOuts from './pages/LPDOuts';
import LPDDetail from './pages/LPDDetail';
import LPD_Modal from './pages/LPDModal';
import LPDFinal from './pages/LPDFinal';
import LPDClearencesheet from './pages/LPDClearencesheet';
import Bank_statement from './pages/rek_koran';
import Bank from './pages/Bank';
import Receipt from './pages/receipt';
import Saldo from './pages/Saldo';
import Hutang_dagang from './pages/HD';
import Rekon_bank from './pages/Reconciliation';
import Daily_statement from './pages/mutasi_harian';
import Mutasi_search from './pages/mutasi_search';
import Mail from './pages/Mail';
import Tasks from './pages/Tasks';
import Periode from './pages/periode';
import Users from './pages/Users';
import Inbox from './pages/Inbox';
import Home from './pages/home';
import Merge_pdf from './pages/Merge_pdf';
import Lpd_pdf from './pages/Lpd_pdf';
import Sarana_toko from './pages/sarana_toko';
import DAT_PR from './pages/dat_pr';
import DAT_PR_unmatch_inv from './pages/DATPR_unmatch_inv';
import Inv_Unmatch_datpr from './pages/Inv_Unmatch_datpr';
import Inv_Unmatch_sarana from './pages/Inv_Unmatch_sarana';
import Berkas_lpd from './pages/Berkas_lpd';
import Modal_LPD from './pages/Modal_LPD';
import Lpd_RAB from './pages/Lpd_RAB';
import Lpd_Sarana from './pages/LPD_Sarana';
import Master_dat_pr from './pages/Master_dat_pr';
import Crypto from './pages/Koin_Crypto';
import MainLayout from './components/MainLayout';
import 'react-datepicker/dist/react-datepicker.css';
import ProtectedRoute from "./components/ProtectedRoute";
import { NoRabProvider } from './contexts/NoRabContext';
import { RightPanelProvider } from './contexts/RightPanelContext';

{/* Game Kakak */}
import SnakeLadderGame from './pages/Edukasi/SnakeLadderGame';
import Game from './pages/Edukasi/HomePage';

function App() {
  return (
    <RightPanelProvider>
      <NoRabProvider>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<Login />} />

          // Game Kakak
          <Route path="/Game" element={<ProtectedRoute><Game /></ProtectedRoute>} />
          <Route path="/edukasi/snake-ladder" element={<ProtectedRoute><SnakeLadderGame /></ProtectedRoute>} />

          <Route path="/" element={<MainLayout />}>
            <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
            <Route path="/lpd/outs" element={<ProtectedRoute><LPDOuts /></ProtectedRoute>} />
            <Route path="/Lpd-detail" element={<ProtectedRoute><LPDDetail /></ProtectedRoute>} />
            <Route path="/Mail" element={<ProtectedRoute><Mail /></ProtectedRoute>} />
            <Route path="/Tasks" element={<ProtectedRoute><Tasks /></ProtectedRoute>} />
            <Route path="/Periode" element={<ProtectedRoute><Periode /></ProtectedRoute>} />
            <Route path="/Users" element={<ProtectedRoute><Users /></ProtectedRoute>} />
            <Route path="/Inbox" element={<ProtectedRoute><Inbox /></ProtectedRoute>} />
            <Route path="/lpd-modal" element={<ProtectedRoute><LPD_Modal /></ProtectedRoute>} />
            <Route path="/monitoring-modal" element={<ProtectedRoute><Modal_LPD /></ProtectedRoute>} />
            <Route path="/monitoring-RAB" element={<ProtectedRoute><Lpd_RAB /></ProtectedRoute>} />
            <Route path="/monitoring-sarana" element={<ProtectedRoute><Lpd_Sarana /></ProtectedRoute>} />
            <Route path="/lpd-final" element={<ProtectedRoute><LPDFinal /></ProtectedRoute>} />
            <Route path="/lpd-cs" element={<ProtectedRoute><LPDClearencesheet /></ProtectedRoute>} />
            <Route path="/bank_statement" element={<ProtectedRoute><Bank_statement /></ProtectedRoute>} />
            <Route path="/bank" element={<ProtectedRoute><Bank /></ProtectedRoute>} />
            <Route path="/Rekon_AR" element={<ProtectedRoute><Receipt /></ProtectedRoute>} />
            <Route path="/hutang_dagang" element={<ProtectedRoute><Hutang_dagang /></ProtectedRoute>} />
            <Route path="/reconciliation" element={<ProtectedRoute><Rekon_bank /></ProtectedRoute>} />
            <Route path="/daily_statement" element={<ProtectedRoute><Daily_statement /></ProtectedRoute>} />
            <Route path="/mutasi_search" element={<ProtectedRoute><Mutasi_search /></ProtectedRoute>} />
            <Route path="/monitoring-saldo" element={<ProtectedRoute><Saldo /></ProtectedRoute>} />
            <Route path="/Merge_pdf" element={<ProtectedRoute><Merge_pdf /></ProtectedRoute>} />
            <Route path="/Lpd_pdf" element={<ProtectedRoute><Lpd_pdf /></ProtectedRoute>} />
            <Route path="/sarana_toko" element={<ProtectedRoute><Sarana_toko /></ProtectedRoute>} />
            <Route path="/dat_pr" element={<ProtectedRoute><DAT_PR /></ProtectedRoute>} />
            <Route path="/Master_dat_pr" element={<ProtectedRoute><Master_dat_pr /></ProtectedRoute>} />
            <Route path="/Berkas_lpd" element={<ProtectedRoute><Berkas_lpd /></ProtectedRoute>} />
            <Route path="/DATPR_Unmatch" element={<ProtectedRoute><DAT_PR_unmatch_inv /></ProtectedRoute>} />
            <Route path="/Inv_Unmatch_datpr" element={<ProtectedRoute><Inv_Unmatch_datpr /></ProtectedRoute>} />
            <Route path="/Inv_Unmatch_sarana" element={<ProtectedRoute><Inv_Unmatch_sarana /></ProtectedRoute>} />
            
            <Route path="/Crypto" element={<ProtectedRoute><Crypto /></ProtectedRoute>} />            
          </Route>
        </Routes>
      </NoRabProvider>
    </RightPanelProvider>
  );
}

export default App;

