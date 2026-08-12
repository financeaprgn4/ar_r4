export const formatDate = (dateString) => {
  if (!dateString) return "";

  const date = new Date(dateString);
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "2-digit",
  }).format(date);
};

export const formatRupiah = (value) => {

  const number = Number(value);

  if (isNaN(number)) {
      return "0";
  }

  return number.toLocaleString("en-US", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
  });

};

export const formatAmount = (value) => {
  const str = String(value || '');
  let num = str.replace(/\D/g, '');
  if (num === '0') return '0';
  num = num.replace(/^0+/, '');
  if (!num) return '0';
  return num.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

export const formatDateForApi = (date) => {
  if (!date) return "";

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};