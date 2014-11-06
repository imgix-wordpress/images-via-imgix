module.exports = function(grunt) {

	var exec = require('child_process').exec,
		fs = require('fs');

	function execRun(cmd, done) {
		exec(cmd, function(err, stdout, stderr) {
			if (err) {
				grunt.fail.fatal(stderr + ' ' + stdout + ' ' + err);
			}
			if (done) {
				done();
			}
		});
	}

	grunt.registerTask('buildzip', 'create a zip file for wordpress...', function() {
		var zips = fs.readdirSync('.'),
			content = fs.readFileSync('plugin/imgix.php', 'UTF-8'),
			version = content.match(/Version: (.+)/)[1];

		for (var i = 0; i < zips.length; i++) {
			if (zips[i].indexOf('.zip') !== -1) {
				grunt.file.delete(zips[i]);
			}
		}

		var zipFile = "imgix_plugin" + version + ".zip";

		execRun('zip -r ' + zipFile + ' plugin/');
	});

	grunt.registerTask('default', 'buildzip');
};
