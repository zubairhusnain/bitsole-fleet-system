export function appendReportQueryParams(qs, params) {
  Object.keys(params).forEach((key) => {
    const value = params[key];
    if (Array.isArray(value)) {
      value.forEach((item) => {
        if (item !== undefined && item !== null && item !== '') {
          qs.append(`${key}[]`, item);
        }
      });
      return;
    }
    if (value !== undefined && value !== null && value !== '') {
      qs.append(key, value);
    }
  });
}

export function openReportExport(path, params = {}) {
  const qs = new URLSearchParams();
  appendReportQueryParams(qs, params);
  const query = qs.toString();
  window.open(query ? `${path}?${query}` : path, '_blank');
}
