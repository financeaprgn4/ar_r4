import React, { useEffect } from "react";
import { Outlet } from "react-router-dom";
import Sidebar from "./Sidebar";
import { SidebarProvider } from "./SidebarContext";
import { CabangProvider } from "../contexts/CabangContext";
import RightPanel from "../components/RightPanel";
import { useRightPanel } from "../contexts/RightPanelContext";

const MainLayout = () => {
  const { show, content, closePanel } = useRightPanel();

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === "Escape" && show) {
        closePanel();
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [show, closePanel]);

  return (
      <CabangProvider>
          <SidebarProvider>
              <div className="
                  relative
                  flex
                  h-screen
                  w-full
                  overflow-hidden
                  bg-gradient-to-br
                  from-blue-500
                  via-purple-500
                  to-pink-500
              ">

                  {/* SIDEBAR */}
                  <Sidebar />

                  {/* AREA KONTEN UTAMA */}
                  <div className="
                      flex
                      flex-col
                      flex-1
                      min-w-0
                      min-h-0
                      overflow-hidden
                  ">

                      {/* OUTLET */}
                      <div className="
                          flex
                          flex-col
                          flex-1
                          min-w-0
                          min-h-0
                          p-4
                          overflow-hidden
                      ">
                          <Outlet />
                      </div>

                  </div>

                  {/* RIGHT PANEL */}
                  <RightPanel show={show} onClose={closePanel}>
                      {content}
                  </RightPanel>

              </div>
          </SidebarProvider>
      </CabangProvider>
  );
};

export default MainLayout;
