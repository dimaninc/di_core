/*

	audio player

*/

var diAudioPlayer = function (_opts) {
    var self = this,
        audioLoaded = false,
        opts = $.extend({
            audio: null,
            onEnd: null
        }, _opts || {});

    this.audio = null;
    this.PAUSE = 1;
    this.PLAY = 2;
    this.END = 3;
    this.UNKNOWN = 0;

    function constructor() {
        if (opts.audio) {
            self.setElement(opts.audio);

            if (opts.onEnd) {
                $(self.audio).on('ended', opts.onEnd);
            }
        }
    }

    this.getElement = function () {
        return this.audio;
    };

    this.setElement = function (e) {
        if (e instanceof jQuery) {
            e = e.length > 0 ? e.get(0) : null;
        }

        if (e) {
            this.audio = e;
            this.audio.crossOrigin = 'anonymous';
        }

        return this;
    };

    this.fixCORS = function (src) {
        src = src || this.audio.src;

        if (src) {
            this.setSource(null);

            this.audio.crossOrigin = 'anonymous';
            this.setSource(src);

            //console.log('diAudioPlayer: CORS fixed');
        }

        return this;
    };

    this.disableCORS = function () {
        if (typeof this.audio.crossOrigin !== 'undefined') {
            this.audio.crossOrigin = null;
        }

        return this;
    };

    this.setSource = function (source) {
        this.audio.src = source;
        if (typeof this.audio.load !== 'undefined') {
            this.audio.load();
        }
        audioLoaded = true;

        return this;
    };

    this.play = function () {
        if (typeof this.audio.play !== 'undefined') {
            if (!audioLoaded) {
                this.audio.load();

                audioLoaded = true;
            }

            var promise = this.audio.play();

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
        this.audio.pause();

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
        if (this.audio.paused) return this.PAUSE;
        else if (this.audio.ended) return this.END;
        else if (this.audio.played) return this.PLAY;
        else return this.UNKNOWN;
    };

    this.getCurrentTime = function () {
        return this.audio.currentTime;
    };

    this.setCurrentTime = function (time) {
        this.audio.currentTime = time;

        return this;
    };

    this.setPosition = function (percent) {
        this.setCurrentTime(this.getDuration() * percent / 100);

        return this;
    };

    this.getDuration = function () {
        return this.audio.duration;
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
        volume = parseFloat(volume) || 0;

        if (volume < 0) volume = 0;
        else if (volume > 1) volume = 1;

        if (this.audio) {
            try {
                this.audio.volume = volume;
            } catch (e) {
                console.log('Error while setting audio volume:', e);
            }
        }

        return this;
    };

    this.getVolume = function () {
        return this.audio.volume;
    };

    constructor();
};
