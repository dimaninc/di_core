import { unslash } from './string-helper';

export function pushRoute(url: string): void {
  const path = `${location.protocol}//${location.host}${url}`;

  if (unslash(location.href) !== unslash(path)) {
    window.history.pushState({ path }, '', path);
  }
}
