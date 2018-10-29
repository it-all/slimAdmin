var gulp = require('gulp');
var plumber = require('gulp-plumber');
var sourcemaps = require('gulp-sourcemaps');
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var cssnano = require('gulp-cssnano');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var babel = require('gulp-babel');

// error function for plumber. prevents having to start gulp watch on error
var onError = function (err) {
    console.log(err);
    this.emit('end');
};

var scssSrc = 'scss/';
var jsSrc = 'js/';
var cssDest = '../public/css';
var jsDest = '../public/js';
// var jsFile = 'scripts.min.js'; // use this only if concatenating all js with .pipe(concat(jsFile))

gulp.task('sass', function() {
    return gulp.src(scssSrc+'*.scss')
        .pipe(plumber({ errorHandler: onError }))
        .pipe(sourcemaps.init())
        .pipe(sass())
        .pipe(autoprefixer())
        .pipe(cssnano())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(cssDest))
});

gulp.task('js', function () {
    return gulp.src(jsSrc+'*.js')
        .pipe(plumber({ errorHandler: onError }))
        .pipe(sourcemaps.init())
        //.pipe(concat(jsFile))
        .pipe(babel())
        .pipe(uglify())
        .pipe(sourcemaps.write("."))
        .pipe(gulp.dest(jsDest));
});

gulp.task('watch', function() {
    gulp.watch(jsSrc+'*.js', gulp.series('js'))
    gulp.watch(scssSrc+'*.scss', gulp.series('sass'));
});
