# initiating plugins
gulp = require('gulp') # Gulp JS
spritesmith = require('gulp.spritesmith') # sprite generator
stylus = require('gulp-stylus') # stylus compiler
csso = require('gulp-csso') # css minify
#imagemin = require('gulp-imagemin') # img minify
uglify = require('gulp-uglify') # js minify
concat = require('gulp-concat') # concat files
gulpBowerFiles = require('gulp-bower-files')
rename = require('gulp-rename')
nib = require('nib')

# base folder
rootFolder = './../../'

versionFile = '_cfg/lib/diStaticBuild.php'

# prefix to all paths
fullPath = (path) ->
    neg = ''

    if (path.substr(0, 1) == '!')
        neg = '!'
        path = path.substr(1)

    neg + rootFolder + path

# css settings
cssFolder = 'css/'
cssOutput = 'styles.css'
cssFiles = [
    '_core/css/dipopups.css'
    'css/jquery/*.css'
    'css/slideshow/*.css'
    'css/stylus/main.css'
]

# sprites settings
spritesImageOutputFolder = 'i/'
spritesMask = 'i/sprite-src/**/*.png'
spritesImageName = 'sprite.png'
spritesCssOutputFolder = cssFolder + 'stylus/blocks/'

# stylus settings
stylusFolder = cssFolder + 'stylus'
stylusFn = stylusFolder + '/main.styl'

# js settings
jsFolder = '_js/'
jsOutput = 'application.js'
jsFiles = [
    '_core/js/**/*.js'
    '_js/jquery/**/*.js'
    '_js/source/**/*.js'
    '!_core/js/admin/*.js'
    '!_core/js/jquery*.js'
    '!_core/js/diCalendar.js'
    '!_core/js/diCart.js'
    '!_core/js/diForm.js'
]

# watch settings
watchSettings =
    'stylus-sprite':
        mask: [
            'i/sprite-src/**/*'
        ]
    'stylus':
        mask: [
            'css/stylus/**/*.styl'
        ]
    'css-concat':
        mask: cssFiles
    'css-min':
        mask: [
            'css/styles.css'
        ]
    'js-concat':
        mask: jsFiles
    'js-min':
        mask: [
            '_js/application.js'
        ]

# sprites
gulp.task 'stylus-sprite', ->
    spriteData = gulp.src fullPath(spritesMask)
        .pipe spritesmith
            imgName: spritesImageName
            cssName: 'sprite.styl'
            cssFormat: 'stylus'
            algorithm: 'binary-tree'
            cssTemplate: (data) ->
                timestamp = (new Date).getTime()
                template = "$sprite-timestamp = #{timestamp}\n"

                for item in data.items
                    template += "$sprite-#{item.name} = #{item.px.offset_x} #{item.px.offset_y} #{item.px.width} #{item.px.height}\n"

                return template
        .on 'error', console.log

    spriteData.img.pipe gulp.dest fullPath(spritesImageOutputFolder)
    spriteData.css.pipe gulp.dest fullPath(spritesCssOutputFolder)

    true

# stylus to css
gulp.task 'stylus', ->
    gulp.src fullPath(stylusFn)
        .pipe(stylus(use: nib()))
        .on 'error', console.log
        .pipe gulp.dest(fullPath(stylusFolder))

    true

# css concat
gulp.task 'css-concat', ->
    gulp.src cssFiles.map (f) -> fullPath(f)
        .pipe(concat(cssOutput))
        .on 'error', console.log
        .pipe gulp.dest(fullPath(cssFolder))

    true

# css minify
gulp.task 'css-min', ->
    gulp.src fullPath(cssFolder + cssOutput)
        .pipe csso()
        .on 'error', console.log
        .pipe(rename({suffix: '.min'}))
        .pipe gulp.dest(fullPath(cssFolder))

    true

# js concat
gulp.task 'js-concat', ->
    gulp.src jsFiles.map (f) -> fullPath(f)
        .pipe concat(jsOutput)
        .pipe gulp.dest(fullPath(jsFolder))

    true

# js minify
gulp.task 'js-min', ->
    gulp.src fullPath(jsFolder + jsOutput)
        .pipe uglify()
        .pipe(rename({suffix: '.min'}))
        .pipe gulp.dest(fullPath(jsFolder))

    true

gulp.task 'version', ->
    require('fs').writeFileSync(rootFolder + versionFile, '<?php\nclass diStaticBuild\n{\n    const VERSION = ' + (new Date()).getTime() + ';\n}');
    true

# pre-build
gulp.task 'build', ['stylus-sprite', 'stylus', 'css-concat', 'css-min', 'js-concat', 'js-min', 'version']

# monitoring
gulp.task 'watch', ->

    for process of watchSettings
        mask = watchSettings[process].mask.map (f) -> fullPath(f)

        watchClosure = do (process, mask) ->
            gulp.watch mask, ->
                gulp.run process
                gulp.run 'version'
                true
            true
