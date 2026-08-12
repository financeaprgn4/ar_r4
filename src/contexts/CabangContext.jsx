import { createContext, useContext, useState } from "react";

const CabangContext = createContext();

export const CabangProvider = ({ children }) => {
  const [cabang, setCabang] = useState(
    sessionStorage.getItem("cabang") || ""
  );

  const changeCabang = (newCabang) => {
    sessionStorage.setItem("cabang", newCabang);
    setCabang(newCabang);
  };

  return (
    <CabangContext.Provider value={{ cabang, changeCabang }}>
      {children}
    </CabangContext.Provider>
  );
};

export const useCabang = () => useContext(CabangContext);