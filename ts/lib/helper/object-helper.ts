import { TObject } from '../../types';

export const findKey = (
  obj: TObject,
  predicate: (arg1: unknown, arg2: string, arg3: TObject) => boolean
) => Object.keys(obj).find((key) => predicate(obj[key], key, obj));

export const removeNullProperties = (obj: TObject): TObject => {
  Object.keys(obj).forEach((key: string) => {
    const value = obj[key];
    const hasProperties = value && Object.keys(value).length > 0;

    if (value === null) {
      delete obj[key];
    } else if (typeof value !== 'string' && hasProperties) {
      removeNullProperties(value as TObject);
    }
  });

  return obj;
};

export function deepCopy<T>(obj: T): T {
  if (obj === null || typeof obj !== 'object') {
    return obj;
  }

  if (Array.isArray(obj)) {
    return obj.map((item) => deepCopy(item)) as T;
  }

  const newObj: Record<string, unknown> = {};
  for (const key in obj) {
    if (Object.prototype.hasOwnProperty.call(obj, key)) {
      newObj[key] = deepCopy(obj[key]);
    }
  }

  return newObj as T;
}

export function getPatchObject(after: TObject, before: TObject): Partial<TObject> {
  const res: TObject = {};

  for (const key in after) {
    if (key in before && before[key] !== after[key]) {
      res[key] = after[key];
    }
  }

  return res;
}
