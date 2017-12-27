# initiating plugins
gulp = require 'gulp'
spriteSmith = require 'gulp.spritesmith' # sprite generator
stylus = require 'gulp-stylus'
#less = require 'gulp-less'
csso = require 'gulp-csso' # css minify
#imagemin = require 'gulp-imagemin'
uglify = require 'gulp-uglify' # js minify
concat = require 'gulp-concat'
bower = require 'gulp-bower'
rename = require 'gulp-rename'
coffee = require 'gulp-coffee'
#babel = require 'gulp-babel'
#newer = require 'gulp-newer'
#sourcemaps = require 'gulp-sourcemaps'
#eslint = require 'gulp-eslint'
nib = require 'nib'
fs = require 'fs'
path = require 'path'
exec = require('child_process').exec
mkdirp = require 'mkdirp'

# base folder
rootFolder = './'
buildFolder = 'build/'
jsBuildFolder = buildFolder + 'js/'
diCoreFolder = '../vendor/dimaninc/di_core/' # _core/
vendorFolder = '../htdocs/assets/vendor/'
bowerFolder = 'bower_components/'

versionFile = '../_cfg/lib/diStaticBuild.php'

# prefix to all paths
fullPath = (path) ->
    neg = ''
    if path.substr(0, 1) is '!'
        neg = '!'
        path = path.substr(1)
    neg + rootFolder + path

# several tasks done checker
tryDone = (tasksDone, tasksTotal, done) ->
    done() if tasksDone is tasksTotal

# delete folder recursive for copy-assets
deleteFolderRecursive = (folder, excludesList = []) ->
    if fs.existsSync folder
        fs.readdirSync(folder).forEach (file, index) ->
            curPath = path.join folder + '/' + file
            return if curPath in excludesList
            if fs.lstatSync(curPath).isDirectory()
                deleteFolderRecursive curPath
            else
                fs.unlinkSync curPath
            return
        fs.rmdirSync folder
    return

copyCoreAssets = (done) ->
    console.log 'Copying CSS'
    gulp
    .src ['../vendor/dimaninc/di_core/css/**/*']
    .pipe gulp.dest '../htdocs/assets/styles/_core/'

    console.log 'Copying Fonts'
    gulp
    .src ['../vendor/dimaninc/di_core/fonts/**/*']
    .pipe gulp.dest '../htdocs/assets/fonts/'

    console.log 'Copying Images'
    gulp
    .src ['../vendor/dimaninc/di_core/i/**/*']
    .pipe gulp.dest '../htdocs/assets/images/_core/'

    console.log 'Copying JS'
    gulp
    .src ['../vendor/dimaninc/di_core/js/**/*']
    .pipe gulp.dest '../htdocs/assets/js/_core/'

    console.log 'Copying Vendor libs'
    gulp
    .src ['../vendor/dimaninc/di_core/vendor/**/*']
    .pipe gulp.dest '../htdocs/assets/vendor/'

    done()

# folders to be created
initFolders = [
    '_admin/_inc/cache'
    '_cfg/cache'
    'db/dump'
    'htdocs/assets/fonts'
    'htdocs/assets/images/_core'
    'htdocs/assets/js/_core'
    'htdocs/assets/styles/_core'
    'htdocs/uploads'
    'log'
    'log/db'
    'log/debug'
]

# stylus settings
stylusFolder = 'stylus/'
stylusMask = '**/*.styl'
stylusBuildFolder = buildFolder + 'styles/'
stylusFn = stylusFolder + 'main.styl'

# less settings
lessFolder = 'less/'
lessMask = '**/*.less'
lessBuildFolder = buildFolder + 'styles/'
lessFn = lessFolder + 'less.less'

# images settings
imagesFolder = 'images/'
fontsFolder = 'fonts/'
videosFolder = 'videos/'

# sprites settings
spritesImageOutputFolder = 'images/'
spritesMask = 'images/sprite-src/**/*.png'
spritesImageName = 'sprite.png'
spritesCssOutputFolder = stylusFolder + 'inc/'
spritesFileName = 'sprite.styl'

# css settings
cssFolder = 'css/'
cssOutput = stylusBuildFolder + 'styles.css'
cssFiles = [
    cssFolder + 'jquery/*.css'
    #diCoreFolder + 'css/dipopups.css'
    stylusBuildFolder + 'main.css'
    #lessBuildFolder + 'less.css'
]

# coffee settings
coffeeFolder = 'coffee/'
coffeeMask = '**/**/*.coffee'

# react settings
reactFolder = 'react/'
reactMask = '**/**/*.jsx'
reactBuildFolder = jsBuildFolder + reactFolder
reactCoreFolder = 'bower_components/react/'
reactCoreFiles = [
    #'react.min.js'
    #'react-dom.min.js'
]

# js settings
jsFolder = 'js/'
jsOutput = 'application.js'
jsOutputMin = 'application.min.js'
jsFiles = reactCoreFiles.map (f) -> reactCoreFolder + f
.concat [
    diCoreFolder + 'js/functions.js'
    #bowerFolder + 'jsep/build/jsep.min.js'
    bowerFolder + 'jsep/src/jsep.js'
    bowerFolder + 'dreampilot/dist/dp.min.js'
    #bowerFolder + 'slick-carousel/slick/'
    jsFolder + '**/**/*.js' # pure js
    jsBuildFolder + '**/*.js' # compiled coffee
    '!' + jsBuildFolder + jsOutput
    '!' + jsBuildFolder + jsOutputMin
]

assetFiles = [
    stylusBuildFolder + 'styles.min.css'
    jsBuildFolder + jsOutputMin
]
assetImageFiles = [
    imagesFolder + '**/*.*'
]
assetFontFiles = [
    fontsFolder + '**/*.*'
]
assetVideoFiles = [
    videosFolder + '**/*.*'
]
assetsTargetFolder = '../htdocs/assets/'

# watch settings
watchSettings =
    'stylus-sprite':
        mask: spritesMask
    'stylus':
        mask: stylusFolder + stylusMask
    'less':
        mask: lessFolder + lessMask
    'css-concat':
        mask: cssFiles
    'css-min':
        mask: cssOutput
    'coffee':
        mask: coffeeFolder + coffeeMask
    #'react':
    #    mask: reactFolder + reactMask
    'js-concat':
        mask: jsFiles
    'js-min':
        mask: jsBuildFolder + jsOutput
    'copy-assets':
        mask: [
            assetFiles
            assetImageFiles
            assetFontFiles
            assetVideoFiles
        ]

gulp.task 'create-folders', (done) ->
    folders = initFolders.map (f) -> '../' + f
    tasksTotal = folders.length
    tasksDone = 0
    for folder in folders
        do (folder) ->
            mkdirp folder, mode: 0o777, (err) ->
                if err
                    console.error err
                else
                    console.log folder, 'created'
                tryDone ++tasksDone, tasksTotal, done

# stylus to css
gulp.task 'stylus', (done) ->
    gulp.src fullPath stylusFn
    .pipe stylus use: nib(), 'include css': true
    .on 'error', console.log
    .pipe gulp.dest fullPath stylusBuildFolder
    .on 'end', -> done()

# less to css
gulp.task 'less', (done) ->
    gulp.src fullPath lessFn
    .pipe less()
    .on 'error', console.log
    .pipe gulp.dest fullPath lessBuildFolder
    .on 'end', -> done()

# sprites
gulp.task 'stylus-sprite', (done) ->
    spriteData = gulp.src fullPath spritesMask
        .pipe spriteSmith
            imgName: spritesImageName
            cssName: spritesFileName
            cssFormat: 'stylus'
            algorithm: 'binary-tree'
            cssTemplate: (data) ->
                timestamp = (new Date).getTime()
                template = "$sprite-timestamp = #{timestamp}\n"

                for item in data.items
                    template += "$sprite-#{item.name} = #{item.px.offset_x} #{item.px.offset_y} #{item.px.width} #{item.px.height}\n"

                template
        .on 'error', console.log
        .on 'end', -> done()

    spriteData.img.pipe gulp.dest fullPath spritesImageOutputFolder
    spriteData.css.pipe gulp.dest fullPath spritesCssOutputFolder

# css concat
gulp.task 'css-concat', (done) ->
    gulp.src cssFiles.map (f) -> fullPath f
    .pipe concat cssOutput
    .on 'error', console.log
    .pipe gulp.dest fullPath rootFolder
    .on 'end', -> done()

# css minify
gulp.task 'css-min', (done) ->
    gulp.src fullPath cssOutput
    .pipe csso()
    .on 'error', console.log
    .pipe rename suffix: '.min'
    .pipe gulp.dest fullPath stylusBuildFolder
    .on 'end', -> done()

# coffee
gulp.task 'coffee', (done) ->
    gulp.src fullPath coffeeFolder + coffeeMask
    .pipe coffee bare: true
    .pipe gulp.dest fullPath jsBuildFolder
    .on 'end', -> done()

# copy bower files to public
gulp.task 'bower-files', (done) ->
    bower
        interactive: true
    .pipe gulp.dest vendorFolder
    .on 'end', -> done()

gulp.task 'react', (done) ->
    gulp.src fullPath reactFolder + reactMask
    .pipe sourcemaps.init()
    .pipe babel
        compact: false
        presets: ['react']
    .pipe sourcemaps.write '.'
    .pipe gulp.dest fullPath reactBuildFolder
    .on 'end', -> done()

###
gulp.task 'es-lint', (done) ->
    gulp.src fullPath reactFolder + reactMask
    .pipe eslint baseConfig: ecmaFeatures: jsx: true
    .pipe eslint.format()
    .pipe eslint.failAfterError()
    .pipe gulp.dest fullPath reactBuildFolder
    .on 'end', -> done()
###

# js concat
gulp.task 'js-concat', (done) ->
    gulp.src jsFiles.map (f) -> fullPath f
    .pipe concat jsOutput
    .pipe gulp.dest fullPath jsBuildFolder
    .on 'end', -> done()

# js minify
gulp.task 'js-min', (done) ->
    gulp.src fullPath jsBuildFolder + jsOutput
    .pipe uglify()
    .pipe rename suffix: '.min'
    .pipe gulp.dest fullPath jsBuildFolder
    .on 'end', -> done()

# killing old assets
gulp.task 'del-old-assets', (done) ->
    excludes = [
        #assetsTargetFolder + '_core'
    ]
    console.log excludes
    deleteFolderRecursive assetsTargetFolder + (sf for sf in [imagesFolder, fontsFolder, videosFolder]), excludes
    done()

# copy core assets to htdocs
gulp.task 'copy-core-assets', (done) ->
    copyCoreAssets done

# copy assets to htdocs
gulp.task 'copy-assets', (done) ->
    tasksTotal = 4
    tasksDone = 0

    gulp.src assetFiles, base: buildFolder
    .on 'error', console.log
    .pipe gulp.dest assetsTargetFolder
    .on 'end', -> tryDone ++tasksDone, tasksTotal, done

    gulp.src assetImageFiles, base: imagesFolder
    .on 'error', console.log
    .pipe gulp.dest assetsTargetFolder + imagesFolder
    .on 'end', -> tryDone ++tasksDone, tasksTotal, done

    gulp.src assetFontFiles, base: fontsFolder
    .on 'error', console.log
    .pipe gulp.dest assetsTargetFolder + fontsFolder
    .on 'end', -> tryDone ++tasksDone, tasksTotal, done

    gulp.src assetVideoFiles, base: videosFolder
    .on 'error', console.log
    .pipe gulp.dest assetsTargetFolder + videosFolder
    .on 'end', -> tryDone ++tasksDone, tasksTotal, done

# create version timestamp php class
gulp.task 'version', (done) ->
    fs.writeFileSync rootFolder + versionFile,
        '<?php\nclass diStaticBuild\n{\n    const VERSION = ' + (new Date()).getTime() + ';\n}'
    done()

# build
gulp.task 'build', gulp.series(
    'bower-files'
    'stylus-sprite'
    'stylus'
    #'less'
    'css-concat'
    'css-min'
    'coffee'
    #'react'
    #'es-lint'
    'js-concat'
    'js-min'
    'version'
    'del-old-assets'
    'copy-core-assets'
    'copy-assets'
)

# init project
gulp.task 'init', gulp.series(
    'create-folders'
    'copy-core-assets'
)

# monitoring
gulp.task 'watch', (done) ->
    for process of watchSettings
        do (process, mask = watchSettings[process].mask) ->
            gulp.watch mask, gulp.series(process)
            .on 'change', (path, stats) ->
                console.log '[' + process + '] changed file ' + path + ''
                gulp.series 'version', 'copy-assets' if process in ['css-min', 'js-min']
            true

    done()

# default
gulp.task 'default', gulp.series(
    'build'
    'watch'
)
