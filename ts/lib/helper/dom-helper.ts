export const isInViewport = (el?: Element) => {
  if (!el) {
    return false;
  }

  const bounding = el.getBoundingClientRect();

  return (
    bounding.top >= 0 &&
    bounding.left >= 0 &&
    bounding.bottom <=
      (window.innerHeight || document.documentElement.clientHeight) &&
    bounding.right <= (window.innerWidth || document.documentElement.clientWidth)
  );
};

export const onInteraction = (callback: VoidFunction, force = false) => {
  let done = false;

  function load() {
    if (done) {
      return;
    }

    done = true;
    window.removeEventListener('scroll', load);
    window.removeEventListener('touchstart', load);
    document.removeEventListener('mouseenter', load);
    document.removeEventListener('click', load);

    callback();
  }

  if (force) {
    callback();
    return;
  }

  window.addEventListener('scroll', load, { passive: true });
  window.addEventListener('touchstart', load);
  document.addEventListener('mouseenter', load);
  document.addEventListener('click', load);
};
