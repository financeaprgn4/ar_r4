import { useState, useRef, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import Swal from "sweetalert2";
import {
  EyeIcon,
  EyeSlashIcon,
  ArrowRightOnRectangleIcon
} from "@heroicons/react/24/outline";

const Login = () => {
  const [showPassword, setShowPassword] = useState(false);
  const usernameRef = useRef(null);
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");

  useEffect(() => {
    if (usernameRef.current) {
      usernameRef.current.focus();
    }
  }, []);

  const handleLogin = async (e) => {
    e.preventDefault();

    try {
      const response = await fetch(`/api/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ username, password })
      });

      const text = await response.text(); // ⬅️ PENTING
      let data = {};

      try {
        data = text ? JSON.parse(text) : {};
      } catch (err) {
        throw new Error("Response server bukan JSON");
      }

      if (!response.ok) {
        throw new Error(data.message || "Login gagal");
      }

      sessionStorage.setItem("username", data.user.username);
      sessionStorage.setItem("nama", data.user.nama);
      sessionStorage.setItem("level_user", data.user.level_user);
      sessionStorage.setItem("cabang", data.user.cabang);
      sessionStorage.setItem("foto", data.user.foto ?? "");

      await Swal.fire({
        icon: "success",
        title: "Login Berhasil",
        timer: 1500,
        showConfirmButton: false,
      });

      navigate("/dashboard");

    } catch (error) {
      Swal.fire({
        icon: "error",
        title: "Login Gagal",
        text: error.message || "Terjadi kesalahan jaringan",
      });
    }
  };

  return (
    <div className="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 overflow-hidden">
      {/* Background Circles */}
      <div className="absolute w-80 h-80 bg-white/20 rounded-full top-[-50px] left-[-50px] blur-3xl animate-pulse"></div>
      <div className="absolute w-96 h-96 bg-white/10 rounded-full bottom-[-60px] right-[-60px] blur-2xl animate-ping"></div>

      {/* Yellow Stars */}
      {[
        { top: "10%", left: "20%" },
        { top: "30%", left: "80%" },
        { top: "60%", left: "10%" },
        { top: "80%", left: "70%" },
        { top: "20%", left: "60%" }
      ].map((pos, i) => (
        <svg
          key={i}
          className="absolute w-3 h-3 text-yellow-300"
          style={{ top: pos.top, left: pos.left }}
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path d="M12 2l1.76 5.43H19l-4.38 3.18 1.67 5.47L12 14.8l-4.29 3.28L9.33 10.6 5 7.43h5.24L12 2z" />
        </svg>
      ))}

      {/* Login Card */}
      <div className="relative bg-white/90 backdrop-blur-md p-8 rounded-xl shadow-xl w-full max-w-md z-10">
        <h2 className="text-2xl font-bold text-center text-blue-600 mb-6">
          Login
        </h2>
        <form onSubmit={handleLogin}>
          <input
            type="text"
            placeholder="Username"
            ref={usernameRef}
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            className="w-full p-2 mb-4 border border-gray-300 rounded"
          />

          <div className="relative mb-4">
            <input
              type={showPassword ? "text" : "password"}
              placeholder="Password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full p-2 border border-gray-300 rounded pr-10"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500"
            >
              {showPassword ? (
                <EyeSlashIcon className="h-5 w-5" />
              ) : (
                <EyeIcon className="h-5 w-5" />
              )}
            </button>
          </div>

          <button
            type="submit"
            className="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition flex items-center justify-center space-x-2"
          >
            <ArrowRightOnRectangleIcon className="h-5 w-5" />
            <span>Login</span>
          </button>
        </form>
      </div>
    </div>
  );
};

export default Login;
