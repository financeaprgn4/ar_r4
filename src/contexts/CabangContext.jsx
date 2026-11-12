
import {
  createContext,
  useContext,
  useState,
} from "react";

const CabangContext = createContext(null);

export const CabangProvider = ({ children }) => {

  const [cabang, setCabang] = useState(
    sessionStorage.getItem("cabang") || ""
  );

  const [role, setRole] = useState(
    sessionStorage.getItem("role") || ""
  );

  // =====================================
  // MENYIMPAN INFORMASI PENGGUNA
  // =====================================

  const setUserContext = (user) => {

    const newCabang = user.cabang || "";
    const newRole = (user.role || "").toUpperCase();

    sessionStorage.setItem("username", user.username || "");
    sessionStorage.setItem("nama", user.nama || "");
    sessionStorage.setItem("level_user", user.level_user || "");
    sessionStorage.setItem("cabang", newCabang);
    sessionStorage.setItem("role", newRole);
    sessionStorage.setItem("foto", user.foto || "");

    setCabang(newCabang);
    setRole(newRole);
  };

  // =====================================
  // MENGUBAH CABANG
  // =====================================

  const changeCabang = (newCabang) => {

    sessionStorage.setItem("cabang", newCabang);

    setCabang(newCabang);
  };

  // =====================================
  // MENGUBAH ROLE
  // =====================================

  const changeRole = (newRole) => {

    const normalizedRole = (newRole || "").toUpperCase();

    sessionStorage.setItem("role", normalizedRole);

    setRole(normalizedRole);
  };

  // =====================================
  // LOGOUT / HAPUS INFORMASI USER
  // =====================================

  const clearUserContext = () => {

    [
      "username",
      "nama",
      "level_user",
      "cabang",
      "role",
      "foto",
    ].forEach((key) => {
      sessionStorage.removeItem(key);
    });

    setCabang("");
    setRole("");
  };

  return (
    <CabangContext.Provider
      value={{
        cabang,
        role,
        setUserContext,
        changeCabang,
        changeRole,
        clearUserContext,
      }}
    >
      {children}
    </CabangContext.Provider>
  );
};

// =====================================
// CUSTOM HOOK
// =====================================

export const useCabang = () => {

  const context = useContext(CabangContext);

  if (!context) {
    throw new Error(
      "useCabang harus digunakan di dalam CabangProvider"
    );
  }

  return context;
};
