type TEventCallback = (props: {
  name: string;
  id: string;
  element: JQuery;
  diPopup: TDiPopups;
}) => void;
type TPopupCallback = (dip: TDiPopups, name: string) => void;

type TDiPopups = {
  disableAutoPosition: VoidFunction;
  setEvent: (id: string, eventName: string, callback: TEventCallback) => this;
  fireEvent: (id: string, eventName: string) => this;
  setCallback: (name: string, callback: TPopupCallback) => this;
  getCallback: (name: string) => TPopupCallback;
  show: (name: string) => boolean;
  hide: (name: string) => boolean;
  hide_all: () => boolean;
  exists: (name: string) => boolean;
  visible: (name: string) => boolean;
};

declare var dip: TDiPopups;
