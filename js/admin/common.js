// @todo use when rest api enabled
function ajaxErrorHandler(err, fallbackMessage) {
  function getMessage() {
    if (typeof err === 'object' && err.message) {
      return err.message;
    }

    const res = JSON.parse(err.responseText);

    return (
      res?.message ||
      (typeof res === 'string' && res) ||
      fallbackMessage ||
      'Error during request'
    );
  }

  const message = getMessage();

  if (!A) {
    console.error(message);
    return;
  }

  A.console.add(message);
}
