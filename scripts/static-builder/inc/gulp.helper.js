var Helper,
  indexOf = [].indexOf || function(item) { for (var i = 0, l = this.length; i < l; i++) { if (i in this && this[i] === item) return i; } return -1; };

Helper = {
  workFolder: './',
  htDocsFolder: 'htdocs',
  coreLocation: 'beyond',
  coreFolders: {
    beyond: '../vendor/dimaninc/di_core/',
    inner: '../_core/'
  },
  masks: {
    css: '**/**/*.css',
    stylus: '**/*.styl',
    sass: '**/*.scss',
    less: '**/*.less',
    sprite: 'images/sprite-src/**/*.png',
    js: '**/**/*.js',
    coffee: '**/**/*.coffee',
    ts: '**/**/*.ts',
    react: '**/**/*.jsx',
    reactTs: '**/**/*.tsx'
  },
  folders: {
    css: 'css/',
    stylus: 'css/admin/stylus/',
    js: 'js/'
  },
  copyGroups: {},
  setHtDocsFolder: function(htDocsFolder) {
    this.htDocsFolder = htDocsFolder;
    return this;
  },
  getHtDocsFolder: function() {
    return this.htDocsFolder;
  },
  setCoreLocation: function(coreLocation) {
    this.coreLocation = coreLocation;
    return this;
  },
  getCoreLocation: function() {
    return this.coreLocation;
  },
  getCoreFolder: function() {
    return this.coreFolders[this.coreLocation];
  },
  setWorkFolder: function(workFolder) {
    this.workFolder = workFolder;
    return this;
  },
  getRootFolder: function() {
    return './';
  },
  getVersionFile: function() {
    return '../_cfg/lib/diStaticBuild.php';
  },
  extend: function() {
    var i, j, key, ref;
    for (i = j = 1, ref = arguments.length; 1 <= ref ? j <= ref : j >= ref; i = 1 <= ref ? ++j : --j) {
      for (key in arguments[i]) {
        if (arguments[i].hasOwnProperty(key)) {
          if (typeof (arguments[0][key] != null) === 'object' && typeof (arguments[i][key] != null) === 'object') {
            this.extend(arguments[0][key], arguments[i][key]);
          } else {
            arguments[0][key] = arguments[i][key];
          }
        }
      }
    }
    return arguments[0];
  },
  filesOfFolder: function(folder, files) {
    if (files == null) {
      files = [];
    }
    return files.map(function(file) {
      return folder + file;
    });
  },
  req: function(module) {
    return require(this.workFolder + '/node_modules/' + module);
  },
  reqLocal: function(module) {
    return require(this.workFolder + '/' + module);
  },
  fullPath: function(path) {
    var k, neg, v;
    if (typeof path === 'object') {
      for (k in path) {
        v = path[k];
        path[k] = this.fullPath(v);
      }
      return path;
    }
    neg = '';
    if (path.substr(0, 1) === '!') {
      neg = '!';
      path = path.substr(1);
    }
    return neg + this.getRootFolder() + path;
  },
  replaceFolderInArray: function(ar, from, to) {
    return ar.map(function(s) {
      return s.replace(new RegExp('^' + from), to);
    });
  },
  tryDone: function(tasksDone, tasksTotal, done) {
    if (tasksDone === tasksTotal) {
      done();
    }
    return this;
  },
  deleteFolderRecursive: function(folder, excludesList) {
    var fs, path;
    if (excludesList == null) {
      excludesList = [];
    }
    if (!fs) {
      fs = require('fs');
    }
    if (!path) {
      path = require('path');
    }
    if (fs.existsSync(folder)) {
      fs.readdirSync(folder).forEach((function(_this) {
        return function(file, index) {
          var curPath;
          curPath = path.join(folder + '/' + file);
          if (indexOf.call(excludesList, curPath) >= 0) {
            return;
          }
          if (fs.lstatSync(curPath).isDirectory()) {
            _this.deleteFolderRecursive(curPath);
          } else {
            fs.unlinkSync(curPath);
          }
        };
      })(this));
      fs.rmdirSync(folder);
    }
    return this;
  },
  copyCoreAssets: function(gulp, done) {
    console.log('Copying CSS');
    gulp.src(['../vendor/dimaninc/di_core/css/**/*', '!../vendor/dimaninc/di_core/css/**/*.styl']).pipe(gulp.dest('../' + this.getHtDocsFolder() + '/assets/styles/_core/'));
    console.log('Copying Fonts');
    gulp.src(['../vendor/dimaninc/di_core/fonts/**/*']).pipe(gulp.dest('../' + this.getHtDocsFolder() + '/assets/fonts/'));
    console.log('Copying Images');
    gulp.src(['../vendor/dimaninc/di_core/i/**/*']).pipe(gulp.dest('../' + this.getHtDocsFolder() + '/assets/images/_core/'));
    console.log('Copying JS');
    gulp.src(['../vendor/dimaninc/di_core/js/**/*']).pipe(gulp.dest('../' + this.getHtDocsFolder() + '/assets/js/_core/'));
    console.log('Copying Vendor libs');
    gulp.src(['../vendor/dimaninc/di_core/vendor/**/*']).pipe(gulp.dest('../' + this.getHtDocsFolder() + '/assets/vendor/'));
    done();
    return this;
  },
  getFolders: function() {
    return ['_admin/_inc/cache', '_cfg/cache', 'db/dump', this.getHtDocsFolder() + '/assets/fonts', this.getHtDocsFolder() + '/assets/images/_core', this.getHtDocsFolder() + '/assets/js/_core', this.getHtDocsFolder() + '/assets/styles/_core', this.getHtDocsFolder() + '/uploads', 'log', 'log/db', 'log/debug'];
  },
  createFolders: function(done) {
    var fn, folder, folders, j, len, mkdirp, tasksDone, tasksTotal;
    if (!mkdirp) {
      mkdirp = this.req('mkdirp');
    }
    folders = this.getFolders().map(function(f) {
      return '../' + f;
    });
    tasksTotal = folders.length;
    tasksDone = 0;
    fn = (function(_this) {
      return function(folder) {
        return mkdirp(folder, 0x1ff, function(err) {
          if (err) {
            console.error(err);
          } else {
            console.log(folder, 'created');
          }
          return _this.tryDone(++tasksDone, tasksTotal, done);
        });
      };
    })(this);
    for (j = 0, len = folders.length; j < len; j++) {
      folder = folders[j];
      fn(folder);
    }
    return this;
  },
  writeVersionFile: function() {
    var fs;
    if (!fs) {
      fs = require('fs');
    }
    fs.writeFileSync(this.getRootFolder() + this.getVersionFile(), '<?php\nclass diStaticBuild\n{\n    const VERSION = ' + (new Date()).getTime() + ';\n}');
    return this;
  },
  assignBasicTasksToGulp: function(gulp) {
    gulp.task('version', (function(_this) {
      return function(done) {
        _this.writeVersionFile();
        return done();
      };
    })(this));
    gulp.task('create-folders', (function(_this) {
      return function(done) {
        return _this.createFolders(done);
      };
    })(this));
    gulp.task('copy-core-assets', (function(_this) {
      return function(done) {
        return _this.copyCoreAssets(gulp, done);
      };
    })(this));
    gulp.task('init', gulp.series('create-folders', 'copy-core-assets'));
    return this;
  },
  assignLessTaskToGulp: function(gulp, opts) {
    var less;
    if (opts == null) {
      opts = {};
    }
    if (!less) {
      less = this.req('gulp-less');
    }
    opts = this.extend({
      fn: null,
      buildFolder: null,
      postCss: false,
      taskName: 'less'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        var l, postcss;
        l = gulp.src(_this.fullPath(opts.fn)).pipe(less());
        if (opts.postCss) {
          postcss = _this.req('gulp-postcss');
          l = l.pipe(postcss([_this.req('precss'), _this.req('postcss-cssnext'), _this.req('cssnano')]));
        }
        return l.on('error', console.log).pipe(gulp.dest(_this.fullPath(opts.buildFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignStylusTaskToGulp: function(gulp, opts) {
    var nib, stylus;
    if (opts == null) {
      opts = {};
    }
    if (!stylus) {
      stylus = this.req('gulp-stylus');
    }
    if (!nib) {
      nib = this.req('nib');
    }
    opts = this.extend({
      fn: null,
      buildFolder: null,
      taskName: 'stylus'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(_this.fullPath(opts.fn)).pipe(stylus({
          use: nib(),
          'include css': true
        })).on('error', console.log).pipe(gulp.dest(_this.fullPath(opts.buildFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignSassTaskToGulp: function(gulp, opts) {
    var e, origSass, sass;
    if (opts == null) {
      opts = {};
    }
    if (!sass) {
      try {
        origSass = this.req('node-sass');
      } catch (error) {
        e = error;
        origSass = this.req('sass');
      }
      sass = this.req('gulp-sass')(origSass);
    }
    opts = this.extend({
      fn: null,
      buildFolder: null,
      taskName: 'sass',
      sassOpts: {}
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(_this.fullPath(opts.fn)).pipe(sass(opts.sassOpts).on('error', sass.logError)).pipe(gulp.dest(_this.fullPath(opts.buildFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignImageMinTaskToGulp: function(gulp, opts) {
    var compressOpts, imagemin;
    if (opts == null) {
      opts = {};
    }
    if (!imagemin) {
      imagemin = this.req('gulp-imagemin');
    }
    opts = this.extend({
      mask: null,
      outputFolder: null,
      taskName: 'imagemin',
      gif: null,
      jpeg: null,
      png: null,
      svg: null
    }, opts);
    compressOpts = [];
    if (opts.gif) {
      compressOpts.push(imagemin.gifsicle(opts.gif));
    }
    if (opts.jpeg) {
      compressOpts.push(imagemin.jpegtran(opts.jpeg));
    }
    if (opts.png) {
      compressOpts.push(imagemin.optipng(opts.png));
    }
    if (opts.svg) {
      compressOpts.push(imagemin.svgo(opts.svg));
    }
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(_this.fullPath(opts.mask)).pipe(compressOpts.length ? imagemin(compressOpts) : imagemin()).on('error', console.log).pipe(gulp.dest(_this.fullPath(opts.outputFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignPngSpritesTaskToGulp: function(gulp, opts) {
    var cssFolder, imgFolder, mask, spriteSmith, taskName, webpOpts;
    if (opts == null) {
      opts = {};
    }
    if (!spriteSmith) {
      spriteSmith = this.req('gulp.spritesmith');
    }
    opts = this.extend({
      taskName: 'stylus-sprite',
      imgFolder: null,
      cssFolder: null,
      mask: null,
      imgName: null,
      cssName: null,
      cssFormat: 'stylus',
      algorithm: 'binary-tree',
      webp: false,
      timestampVarSuffix: '',
      cssTemplate: function(data) {
        var item, j, len, ref, template, timestamp;
        timestamp = (new Date).getTime();
        template = "$sprite" + opts.timestampVarSuffix + "-timestamp = " + timestamp + "\n";
        ref = data.items;
        for (j = 0, len = ref.length; j < len; j++) {
          item = ref[j];
          template += "$sprite-" + item.name + " = " + item.px.offset_x + " " + item.px.offset_y + " " + item.px.width + " " + item.px.height + "\n";
        }
        return template;
      }
    }, opts);
    taskName = opts.taskName;
    mask = opts.mask;
    imgFolder = opts.imgFolder;
    cssFolder = opts.cssFolder;
    webpOpts = opts.webp;
    delete opts.taskName;
    delete opts.mask;
    delete opts.cssFolder;
    delete opts.imgFolder;
    delete opts.webp;
    gulp.task(taskName, (function(_this) {
      return function(done) {
        var spriteData;
        spriteData = gulp.src(_this.fullPath(mask)).pipe(spriteSmith(opts)).on('error', console.log).on('end', function() {
          return done();
        });
        spriteData.img.pipe(gulp.dest(_this.fullPath(imgFolder))).on('end', function() {
          var rename, webp;
          if (webpOpts) {
            if (!webp) {
              webp = _this.req('gulp-webp');
            }
            if (!rename) {
              rename = _this.req('gulp-rename');
            }
            return gulp.src(imgFolder + opts.imgName).pipe(webp()).pipe(rename({
              suffix: '.png'
            })).pipe(gulp.dest(imgFolder));
          }
        });
        return spriteData.css.pipe(gulp.dest(_this.fullPath(cssFolder)));
      };
    })(this));
    return this;
  },
  assignCssConcatTaskToGulp: function(gulp, opts) {
    var concat;
    if (opts == null) {
      opts = {};
    }
    if (!concat) {
      concat = this.req('gulp-concat');
    }
    opts = this.extend({
      files: [],
      output: null,
      taskName: 'css-concat'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(opts.files.map(function(f) {
          return _this.fullPath(f);
        })).pipe(concat(opts.output)).on('error', console.log).pipe(gulp.dest(_this.fullPath(_this.getRootFolder()))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignCssMinTaskToGulp: function(gulp, opts) {
    var csso, rename;
    if (opts == null) {
      opts = {};
    }
    if (!csso) {
      csso = this.req('gulp-csso');
    }
    if (!rename) {
      rename = this.req('gulp-rename');
    }
    opts = this.extend({
      input: null,
      outputFolder: null,
      taskName: 'css-min',
      options: {}
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(_this.fullPath(opts.input)).pipe(csso(opts.options)).on('error', console.log).pipe(rename({
          suffix: '.min'
        })).pipe(gulp.dest(_this.fullPath(opts.outputFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  cleanCoffeeBuildDirectory: function(folder) {
    this.deleteFolderRecursive(this.fullPath(folder));
    return this;
  },
  assignCoffeeTaskToGulp: function(gulp, opts) {
    var coffee;
    if (opts == null) {
      opts = {};
    }
    if (!coffee) {
      coffee = this.req('gulp-coffee');
    }
    opts = this.extend({
      folder: null,
      mask: null,
      jsBuildFolder: null,
      cleanBefore: false,
      taskName: 'coffee'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        if (opts.cleanBefore) {
          _this.cleanCoffeeBuildDirectory(opts.jsBuildFolder);
        }
        return gulp.src(_this.fullPath(opts.folder + opts.mask)).pipe(coffee({
          bare: true
        })).pipe(gulp.dest(_this.fullPath(opts.jsBuildFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignEs6TaskToGulp: function(gulp, opts) {
    var babel, sourcemaps;
    if (opts == null) {
      opts = {};
    }
    if (!babel) {
      babel = this.req('gulp-babel');
    }
    if (!sourcemaps) {
      sourcemaps = this.req('gulp-sourcemaps');
    }
    opts = this.extend({
      folder: null,
      mask: null,
      jsBuildFolder: null,
      taskName: 'es6'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(_this.fullPath(opts.folder + opts.mask)).pipe(sourcemaps.init()).pipe(babel({
          compact: false,
          presets: ['@babel/env']
        })).pipe(sourcemaps.write('.')).pipe(gulp.dest(_this.fullPath(opts.jsBuildFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignTypescriptTaskToGulp: function(gulp, opts) {
    var concat, merge, sourcemaps, ts, tsProject;
    if (opts == null) {
      opts = {};
    }
    if (!ts) {
      ts = this.req('gulp-typescript');
    }
    if (!sourcemaps) {
      sourcemaps = this.req('gulp-sourcemaps');
    }
    if (!concat) {
      concat = this.req('gulp-concat');
    }
    if (!merge) {
      merge = this.req('merge2');
    }
    tsProject = ts.createProject('tsconfig.json');
    opts = this.extend({
      buildFolder: null,
      destFilename: 'app.js',
      taskName: 'typescript'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function() {
        var tsResult;
        tsResult = tsProject.src().pipe(sourcemaps.init()).pipe(tsProject());
        return merge([tsResult.dts.pipe(gulp.dest(_this.fullPath(opts.buildFolder + '/definitions'))), tsResult.js.pipe(concat(opts.destFilename)).pipe(sourcemaps.write('.')).pipe(gulp.dest(_this.fullPath(opts.buildFolder)))]);
      };
    })(this));
    return this;
  },
  assignWebpackTypescriptTaskToGulp: function(gulp, opts) {
    var webpack;
    if (opts == null) {
      opts = {};
    }
    webpack = this.req('webpack-stream');
    opts = this.extend({
      entryFiles: [],
      buildFolder: null,
      taskName: 'typescript'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function() {
        return gulp.src(opts.entryFiles).pipe(webpack(_this.reqLocal('webpack.config.js'))).pipe(gulp.dest(_this.fullPath(opts.buildFolder)));
      };
    })(this));
    return this;
  },
  assignJavascriptConcatTaskToGulp: function(gulp, opts) {
    var concat;
    if (opts == null) {
      opts = {};
    }
    if (!concat) {
      concat = this.req('gulp-concat');
    }
    opts = this.extend({
      files: [],
      output: null,
      taskName: 'js-concat'
    }, opts);
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(opts.files.map(function(f) {
          return _this.fullPath(f);
        })).pipe(concat(opts.output)).on('error', console.log).pipe(gulp.dest(_this.fullPath(_this.getRootFolder()))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  assignJavascriptMinTaskToGulp: function(gulp, opts) {
    var rename, uglify, uglifyOpts;
    if (opts == null) {
      opts = {};
    }
    opts = this.extend({
      input: null,
      outputFolder: null,
      taskName: 'js-min',
      es6: false
    }, opts);
    uglify = opts.es6 ? this.req('gulp-terser') : !uglify ? this.req('gulp-uglify') : void 0;
    uglifyOpts = opts.es6 ? {
      ecma: 2015,
      keep_classnames: true,
      keep_fnames: true,
      output: {
        comments: false
      }
    } : {};
    if (!rename) {
      rename = this.req('gulp-rename');
    }
    gulp.task(opts.taskName, (function(_this) {
      return function(done) {
        return gulp.src(_this.fullPath(opts.input)).pipe(uglify(uglifyOpts)).on('error', console.log).pipe(rename({
          suffix: '.min'
        })).pipe(gulp.dest(_this.fullPath(opts.outputFolder))).on('end', function() {
          return done();
        });
      };
    })(this));
    return this;
  },
  addSimpleCopyTaskToGulp: function(gulp, opts) {
    if (opts == null) {
      opts = {};
    }
    opts = this.extend({
      groupId: null,
      files: [],
      baseFolder: null,
      destFolder: null,
      done: null
    }, opts);
    if (this.copyGroups[opts.groupId] == null) {
      this.copyGroups[opts.groupId] = {
        total: 0,
        done: 0
      };
    }
    this.copyGroups[opts.groupId].total++;
    gulp.src(opts.files, {
      base: opts.baseFolder
    }).on('error', console.log).pipe(gulp.dest(opts.destFolder)).on('end', (function(_this) {
      return function() {
        return _this.tryDone(++_this.copyGroups[opts.groupId].done, _this.copyGroups[opts.groupId].total, opts.done);
      };
    })(this));
    return this;
  },
  assignBowerFilesTaskToGulp: function(gulp, opts) {
    var bower;
    if (opts == null) {
      opts = {};
    }
    if (!bower) {
      bower = this.req('gulp-bower');
    }
    opts = this.extend({
      outputFolder: null,
      taskName: 'bower-files'
    }, opts);
    gulp.task(opts.taskName, function(done) {
      return bower({
        interactive: true
      }).pipe(gulp.dest(opts.outputFolder)).on('end', function() {
        return done();
      });
    });
    return this;
  },
  assignAdminStylusTaskToGulp: function(gulp, opts) {
    var watch;
    if (opts == null) {
      opts = {};
    }
    opts = this.extend({
      skipCopyCoreAssets: false
    }, opts);
    watch = {
      'admin-stylus': {
        mask: this.getCoreFolder() + this.folders.stylus + Helper.masks.stylus,
        hasProcess: true
      },
      'admin-css': {
        mask: this.getCoreFolder() + this.folders.css + Helper.masks.css
      },
      'admin-js': {
        mask: this.getCoreFolder() + this.folders.js + Helper.masks.js
      }
    };
    this.assignStylusTaskToGulp(gulp, {
      taskName: 'admin-stylus',
      fn: [this.getCoreFolder() + this.folders.stylus + 'admin.styl', this.getCoreFolder() + this.folders.stylus + 'login.styl'],
      buildFolder: this.getCoreFolder() + 'css/admin/'
    });
    gulp.task('admin-assets-watch', function(done) {
      var fn, process;
      fn = function(process, mask, hasProcess) {
        var args, task;
        args = [];
        if (hasProcess) {
          args.push(process);
        }
        if (!opts.skipCopyCoreAssets) {
          args.push('copy-core-assets');
        }
        if (args.length) {
          task = gulp.series.apply(gulp, args);
          gulp.watch(mask, task);
        }
        return true;
      };
      for (process in watch) {
        fn(process, watch[process].mask, watch[process].hasProcess);
      }
      return done();
    });
    return this;
  }
};

module.exports = Helper;

//# sourceMappingURL=gulp.helper.js.map
