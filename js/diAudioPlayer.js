/*

	audio player

*/

var diAudioPlayer = function (_opts) {
    var self = this,
        audio = null,
        audioLoaded = false,
        opts = $.extend({
            audio: null,
            onEnd: null
        }, _opts || {});

    this.PAUSE = 1;
    this.PLAY = 2;
    this.END = 3;
    this.UNKNOWN = 0;

    function constructor() {
        if (opts.audio) {
            self.setElement(opts.audio);

            if (opts.onEnd) {
                $(audio).on('ended', opts.onEnd);
            }
        }
    }

    this.getElement = function () {
        return audio;
    };

    this.setElement = function (e) {
        if (e instanceof jQuery) {
            e = e.length > 0 ? e.get(0) : null;
        }

        audio = e;
        audio.crossOrigin = 'anonymous';

        return this;
    };

    this.fixCORS = function (src) {
        src = src || audio.src;

        if (src) {
            this.setSource(null);

            audio.crossOrigin = 'anonymous';
            this.setSource(src);

            //console.log('diAudioPlayer: CORS fixed');
        }

        return this;
    };

    this.disableCORS = function () {
        if (typeof audio.crossOrigin !== 'undefined') {
            audio.crossOrigin = null;
        }

        return this;
    };

    this.setSource = function (source) {
        audio.src = source;
        audio.load();
        audioLoaded = true;

        return this;
    };

    this.play = function () {
        if (typeof audio.play !== 'undefined') {
            if (!audioLoaded) {
                audio.load();

                audioLoaded = true;
            }

            var promise = audio.play();

            if (promise !== undefined) {
                promise
                    .then(function () {
                        //console.log('diAudioPlayer: audio track started');
                    })
                    .catch(function (error) {
                        console.log('diAudioPlayer: error playing audio track', error);
                    });
            }
        } else {
            throw 'Audio player not initialized';
        }

        return this;
    };

    this.pause = function () {
        audio.pause();

        return this;
    };

    this.toggleState = function () {
        if (this.getState() != this.PAUSE) {
            this.pause();
        }
        else {
            this.play();
        }

        return this;
    };

    this.getState = function () {
        if (audio.paused) return this.PAUSE;
        else if (audio.ended) return this.END;
        else if (audio.played) return this.PLAY;
        else return this.UNKNOWN;
    };

    this.getCurrentTime = function () {
        return audio.currentTime;
    };

    this.setCurrentTime = function (time) {
        audio.currentTime = time;

        return this;
    };

    this.setPosition = function (percent) {
        this.setCurrentTime(this.getDuration() * percent / 100);

        return this;
    };

    this.getDuration = function () {
        return audio.duration;
    };

    this.isPlayed = function () {
        return this.getState() == this.PLAY;
    };

    this.isPaused = function () {
        return this.getState() == this.PAUSE;
    };

    this.isEnded = function () {
        return this.getState() == this.END;
    };

    /* 0.0 ... 1.0 */
    this.setVolume = function (volume) {
        volume *= 1;

        if (volume < 0) volume = 0;
        else if (volume > 1) volume = 1;

        audio.volume = volume;

        return this;
    };

    this.getVolume = function () {
        return audio.volume;
    };

    constructor();
};
