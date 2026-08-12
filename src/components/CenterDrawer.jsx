import { motion, AnimatePresence } from 'framer-motion';
const CenterDrawer = ({
  isOpen,
  onClose,
  children,
  borderColor = 'rgba(0, 0, 0, 0.5)',
  bodyBg = 'rgba(255, 255, 255, 0.95)',
  widthClass = 'max-w-2xl',
}) => {
  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            className="fixed inset-0 z-50 flex items-center justify-center"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            style={{
              backgroundColor: 'rgba(0,0,0,0.3)',
              backdropFilter: 'blur(2px)',
            }}
          >
            <motion.div
              className={`w-full ${widthClass} max-h-[90vh] overflow-y-auto shadow-lg border-y-[10px]`}
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.9, opacity: 0 }}
              transition={{ type: 'spring', duration: 0.5 }}
              style={{
                backgroundColor: bodyBg,
                borderTopColor: borderColor,
                borderBottomColor: borderColor,
                borderTopStyle: 'solid',
                borderBottomStyle: 'solid',
              }}
            >
              <div className="p-4 relative">
                {children}
              </div>
            </motion.div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
};

export default CenterDrawer;
