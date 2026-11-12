
import './index.css';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import './config/fetchPatch';

import App from './App.jsx';
import { CabangProvider } from './contexts/CabangContext';

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <BrowserRouter basename={import.meta.env.BASE_URL}>
            <CabangProvider>
                <App />
            </CabangProvider>
        </BrowserRouter>
    </StrictMode>,
);
