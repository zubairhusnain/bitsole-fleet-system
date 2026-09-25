/**
 * Cancel in-flight axios requests when the user leaves the page or starts a new search.
 */
export function createCancellableRequest() {
  let controller = null;

  function cancel() {
    if (controller) {
      controller.abort();
      controller = null;
    }
  }

  function nextSignal() {
    cancel();
    controller = new AbortController();
    return controller.signal;
  }

  return { cancel, nextSignal };
}

export function isRequestAborted(error) {
  return error?.code === 'ERR_CANCELED'
    || error?.name === 'CanceledError'
    || error?.name === 'AbortError'
    || error?.response?.status === 499;
}
