export type TUnknownArray = unknown[];
export type TObject = Record<string, unknown>;
export type TStringObject = Record<string, string>;
export type TStringNumberObject = Record<string, number>;
export type TCallback = <Type>(value: Type) => Type;
export type TCollection<T> = T[];
