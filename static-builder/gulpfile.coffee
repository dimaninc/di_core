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

# base folder
rootFolder = './../'

# prefix to all paths
fullPath = (path) ->
    neg = ''

    if path.substr(0, 1) is '!'
        neg = '!'
        path = path.substr 1

    neg + rootFolder + path

# css settings
cssFolder = 'css/'
cssOutput = 'full-admin.css'
cssFiles = [
    'css/admin/*.css'
    'css/stylus/buttons.css'
]

# sprites settings
spritesImageOutputFolder = 'i/admin/'
spritesMask = 'i/admin/sprite-src/*.png'
spritesImageName = 'buttons.png'
spritesCssOutputFolder = cssFolder + 'stylus/'

# stylus settings
stylusFolder = cssFolder + 'stylus'
stylusFn = stylusFolder + '/buttons.styl'

# watch settings
watchSettings =
    'stylus-sprite':
        mask: [
            spritesMask
        ]
    'stylus':
        mask: [
            stylusFolder + '**/*.styl'
        ]
    'css-concat':
        mask: cssFiles
    'css-min':
        mask: [
            'css/admin/full-admin.css'
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
                vars = "$admin-buttons-timestamp = #{timestamp}\n"
                buttons = ".nicetable-button\n  sprite $admin-buttons-empty\n"

                for item in data.items
                    vars += "$admin-buttons-#{item.name} = #{item.px.offset_x} #{item.px.offset_y} #{item.px.width} #{item.px.height}\n"
                    components = item.name.split "-"
                    spriteCall = "sprite $admin-buttons-#{item.name}\n"

                    if components.length > 1
                        buttons += "  &[data-action=\"#{components[0]}\"][data-state=\"#{components[1]}\"]\n    "
                    else
                        buttons += "  &[data-action=\"#{item.name}\"]\n    "

                    buttons += spriteCall

                return vars + "\n" + buttons
        .on 'error', console.log

    spriteData.img.pipe gulp.dest fullPath(spritesImageOutputFolder)
    spriteData.css.pipe gulp.dest fullPath(spritesCssOutputFolder)

    true

# stylus to css
gulp.task 'stylus', ->
    gulp.src fullPath stylusFn
        .pipe stylus use: ['nib']
        .on 'error', console.log
            .pipe gulp.dest fullPath stylusFolder

    true

# css concat
gulp.task 'css-concat', ->
    gulp.src cssFiles.map (f) -> fullPath(f)
        .pipe concat cssOutput
        .on 'error', console.log
            .pipe gulp.dest fullPath cssFolder

    true

# css minify
gulp.task 'css-min', ->
    gulp.src fullPath cssFolder + cssOutput
        .pipe csso()
        .on 'error', console.log
            .pipe rename suffix: '.min'
            .pipe gulp.dest fullPath cssFolder

    true

# pre-build
gulp.task 'build', ->
    gulp.run 'stylus-sprite'
    gulp.run 'stylus'
    gulp.run 'css-concat'
    gulp.run 'css-min'

# monitoring
gulp.task 'watch', ->

    for process of watchSettings
        mask = watchSettings[process].mask.map (f) -> fullPath(f)

        watchClosure = do (process, mask) ->
            gulp.watch mask, ->
                gulp.run process
                true
            true
