import { Navigate } from "react-router-dom";

const ProtectedRoute = ({ children }) => {
  const username = sessionStorage.getItem("username");

  if (!username) {
    return <Navigate to="/login" replace />;
  }

  return children;
};

export default ProtectedRoute;
