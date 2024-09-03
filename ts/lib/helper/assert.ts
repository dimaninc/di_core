import { TObject, TUnknownArray } from '@dicore/types';

export const isUndefined = (value: unknown): value is undefined =>
    typeof value === 'undefined';

export const isDefined = <T>(value: T): value is Exclude<T, undefined> =>
    typeof value !== 'undefined';

export const isNotNull = <T>(value: T): value is Exclude<T, null> => value !== null;

export const isNull = (value: unknown): value is null => value === null;

export const isUnknownArray = (value: unknown): value is TUnknownArray =>
    Array.isArray(value);

export const isUnknownObject = (value: unknown): value is TObject =>
    typeof value === 'object' && value !== null && !isUnknownArray(value);

export const hasProperty =
    <Y extends PropertyKey>(propertyName: Y) =>
    <X>(value: unknown): value is X & Record<Y, unknown> =>
        isUnknownObject(value) && propertyName in value;
