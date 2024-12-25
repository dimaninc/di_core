declare var dip: {
  disableAutoPosition: VoidFunction;
  setEvent: (id: string, eventName: string, callback: VoidFunction) => this;
  fireEvent: (id: string, eventName: string) => this;
  setCallback: (name: string, callback: VoidFunction) => this;
  getCallback: (name: string) => VoidFunction;
  show: (name: string) => boolean;
  hide: (name: string) => boolean;
  hide_all: () => boolean;
  exists: (name: string) => boolean;
  visible: (name: string) => boolean;
};
