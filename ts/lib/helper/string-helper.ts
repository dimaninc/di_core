import { TStringObject } from '../../types';

export const lead0 = (num: number | string) =>
  String(num).length === 1 ? `0${num}` : String(num);

export function replaceBulk(
  str: string,
  findReplaces: TStringObject,
  ignoreCase = false
): string {
  const regexes = [];
  const map: TStringObject = {};
  const flags = ['g'];
  ignoreCase && flags.push('i');

  for (const [find, replace] of Object.entries(findReplaces)) {
    regexes.push(find.replace(/([-[\]{}()*+?.\\^$|#,])/g, '\\$1'));
    map[find] = replace;
  }
  const regex = new RegExp(regexes.join('|'), flags.join(''));

  return str.replace(regex, (matched) => map[matched]);
}

export function replaceParams(str: string, params: TStringObject): string {
  const paramsWithBraces = Object.fromEntries(
    Object.entries(params).map(([k, v]) => [`{${k}}`, v])
  );

  return replaceBulk(str, paramsWithBraces);
}

export function base64toBlob(b64Data: string, contentType = '', sliceSize = 512) {
  const x = b64Data.indexOf(',');
  if (x !== -1) {
    if (!contentType) {
      contentType = b64Data.substring(5, x);
    }
    b64Data = b64Data.substring(x + 1);
  }
  const byteCharacters = atob(b64Data);
  const byteArrays = [];

  for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
    const slice = byteCharacters.slice(offset, offset + sliceSize);

    const byteNumbers = Array(slice.length);
    for (let i = 0; i < slice.length; i++) {
      byteNumbers[i] = slice.charCodeAt(i);
    }

    const byteArray = new Uint8Array(byteNumbers);
    byteArrays.push(byteArray);
  }

  return new Blob(byteArrays, { type: contentType });
}

export function complexStr(ar: unknown[], glue = ', '): string {
  return ar.filter(Boolean).join(glue);
}

type TCnArg = string | number | boolean | null | undefined | TCnArg[] | Record<string, unknown>;

export const cn = (...args: TCnArg[]): string => {
  const classes: string[] = [];

  for (const arg of args) {
    if (!arg) {
      continue;
    }

    if (typeof arg === 'string' || typeof arg === 'number') {
      classes.push(String(arg));
    } else if (Array.isArray(arg)) {
      const inner = cn(...arg);
      if (inner) {
        classes.push(inner);
      }
    } else if (typeof arg === 'object') {
      for (const [key, value] of Object.entries(arg)) {
        if (value) {
          classes.push(key);
        }
      }
    }
  }

  return classes.join(' ');
};

export function slash(path: string, ending = true): string {
  if (ending && !path.endsWith('/')) {
    return `${path}/`;
  }

  if (!ending && !path.startsWith('/')) {
    return `/${path}`;
  }

  return path;
}

export function unslash(path: string, ending = true): string {
  return path.replace(ending ? /\/+$/g : /^\/+/g, '');
}

export function digitCase(
  x: number,
  s1: string,
  s2: string,
  s3: Nullable<string> = null,
  returnOnlySuffix = false
): string {
  if (s3 === null) {
    s3 = s2;
  }

  const x0: number = x;
  x = x % 100;

  if (x % 10 === 1 && x !== 11) {
    return returnOnlySuffix ? s1 : `${x0} ${s1}`;
  } else if (x % 10 >= 2 && x % 10 <= 4 && x !== 12 && x !== 13 && x !== 14) {
    return returnOnlySuffix ? s2 : `${x0} ${s2}`;
  } else {
    return returnOnlySuffix ? s3 : `${x0} ${s3}`;
  }
}

export function isStringMatchToRegexps(s: string, regexps: RegExp[]) {
  for (const r of regexps) {
    if (r.test(s)) {
      return true;
    }
  }

  return false;
}
