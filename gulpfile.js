require('es6-promise').polyfill();

var gulp = require('gulp');
var rename = require('gulp-rename');
var watch = require('gulp-watch');
var browserify = require('browserify');
var watchify = require('watchify');
var source = require('vinyl-source-stream')
var less = require('gulp-less');

var postcss = require('gulp-postcss');
var autoprefixer = require('autoprefixer');

var pkg = require('./package.json');

var plugin = {
    less: './assets/less/app.less',
    lesss: './assets/less/**/*.less',

    js: './assets/js/app.js',

    dest: './build'
}

function getBrowserify(entry) {
    // Konfigurējam browserify
    return browserify({
        entries: [entry],
        // These params are for watchify
        cache: {}, 
        packageCache: {}
    })
}

function bundle(browserify, module, dest) {
    browserify
        .bundle()
        .on('error', function(er){
            
            console.log(er.message);
            
        })
        .pipe(source('app.js'))
        .pipe(rename(module+'.min-'+pkg.version+'.js'))
        .pipe(gulp.dest(dest));
}

function bundleLess(entry, module, dest) {
    gulp.src(entry)
        .pipe(
            less()
                .on('error', function(er){
                    console.log(er.type+': '+er.message);
                    console.log(er.filename+':'+er.line);
                })
        )
        .pipe(rename(module+'.min-'+pkg.version+'.css'))
        .pipe(gulp.dest(dest));
}

function autoPrefixCss(src, dest) {
    var processors = [
        autoprefixer({browsers: ['last 6 version']})
    ];

    return gulp.src(src)
        .pipe(postcss(processors))
        .pipe(gulp.dest(dest));
}

gulp.task('js', function(){
    bundle(getBrowserify(plugin.js), 'app', plugin.dest);
});

gulp.task('less', function(){
    bundleLess(plugin.less, 'app', plugin.dest);
});

gulp.task('watchjs', function(){
    var w = watchify(getBrowserify(plugin.js));
    
    w.on('update', function(){
        bundle(w, 'app', plugin.dest);
        console.log('js files updated');
    });

    w.bundle().on('data', function() {});
});

gulp.task('watchless', function(){
    watch([plugin.lesss], function(){
        console.log('less files updated');
        bundleLess(plugin.less, 'app', plugin.dest);
    });
});

gulp.task('autoprefixcss', function(){
    autoPrefixCss(plugin.dest+'/app.min-'+pkg.version+'.css', plugin.dest);
});

gulp.task('default', ['watchless', 'watchjs']);
gulp.task('dist', ['less', 'js']);