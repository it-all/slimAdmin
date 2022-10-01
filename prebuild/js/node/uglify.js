var jsFiles = [
    "sortTable",
    "uiHelper"
];
process(jsFiles);

function process(files) {
    var arrayLength = files.length;
    for (var i = 0; i < arrayLength; i++) {
        processFile(files[i]);
    }
}

function processFile(jsFilename) {
    var fs = require('fs');
    var UglifyJS = require('uglify-js');
    var jsDir = "prebuild/js/";
    var jsFile = jsFilename + ".js";
    var outFile = jsFile;
    var mapFile = outFile + ".map";
    var outDir = "public/js/";
    var code = fs.readFileSync(jsDir+jsFile, "utf8");
    var result = UglifyJS.minify(code, {
        sourceMap: {
            filename: outFile,
            url: mapFile
        }
    });
    fs.writeFile(outDir+outFile, result.code, function(err) {
        if(err) {
            console.log(err);
        } else {
            console.log(outFile + " was successfully saved.");
        }
    });
    fs.writeFile(outDir+mapFile, result.map, function(err) {
        if(err) {
            console.log(err);
        } else {
            console.log(mapFile + " file was successfully saved.");
        }
    });
    
}

//view the output
// console.log(result.code);
