import RightPanel from '../components/RightPanel';
import ViewLPD from '../components/ViewLPD';
import CreateLPD from '../components/CreateLPD';
import { useRightPanel } from '../context/RightPanelContext';

const RightPanelContainer = ({
  dataMap,
  formatDate,
  formatRupiah,
  inputValues,
  handleChange,
  handleSave,
  handleStore,
  handleReset,
  total_estimasi,
  total_realisasi
}) => {
  const { show, closePanel, mode, selectedSite } = useRightPanel();

  return (
    <RightPanel show={show} onClose={closePanel}>
      {mode === 'view' && (
        <ViewLPD
          selectedSite={selectedSite}
          dataMap={dataMap}
          formatDate={formatDate}
          formatRupiah={formatRupiah}
          inputValues={inputValues}
          total_estimasi={total_estimasi}
          total_realisasi={total_realisasi}
          handleChange={handleChange}
          handleSave={handleSave}
          show={show}
          mode={mode}
        />
      )}

      {mode === 'add' && (
        <CreateLPD
          dataMap={dataMap}
          inputValues={inputValues}
          handleStore={handleStore}
          formatDate={formatDate}
          formatRupiah={formatRupiah}
          handleChange={handleChange}
          handleReset={handleReset}
          show={show}
          mode={mode}
        />
      )}
    </RightPanel>
  );
};

export default RightPanelContainer;
