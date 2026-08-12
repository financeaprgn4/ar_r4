import { createContext, useContext, useState } from 'react';

const NoRabContext = createContext();

export const NoRabProvider = ({ children }) => {
  const [noRab, setNoRab] = useState(localStorage.getItem('no_rab') || '');

  const updateNoRab = (value) => {
    setNoRab(value);
    localStorage.setItem('no_rab', value);
  };

  return (
    <NoRabContext.Provider value={{ noRab, updateNoRab }}>
      {children}
    </NoRabContext.Provider>
  );
};

// Hook untuk akses context
export const useNoRab = () => useContext(NoRabContext);
