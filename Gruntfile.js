module.exports = function(grunt){

    require("matchdep").filterDev("grunt-*").forEach(grunt.loadNpmTasks);

    var jsFiles = [
    	'assets/main.js'
    ];

    var cssFiles = [
    	'assets/main.css'
    ];

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        tag: {
	    	banner: '/*! <%= pkg.name %> v<%= pkg.version %> | (c) <%= grunt.template.today(\'yyyy\') %> Kaspars Bulins http://webit.lv */\n',
	    },
	    concat: {
	    	options: {
			    banner: '<%= tag.banner %>',
			    separator: ';'
			},
	      	main: {
	        	src: jsFiles,
	        	dest: 'build/site.min.js'
	      	}
	    },
        uglify: {
		    build: {
		        files: {
		            'build/site.min.js': jsFiles
		        },
			    options: {
			    	banner: '<%= tag.banner %>'
			    }
		    }
		},
		cssmin: {
			options: {
				target: 'build/..'
			},
			build: {
				files: {
					'build/site.min.css': cssFiles
				}
			}
		},
		copy: {
			js: {
				src: 'build/site.min.js', 
				dest: 'build/site.min-<%= pkg.version %>.js'
			},
			css: {
				src: 'build/site.min.css', 
				dest: 'build/site.min-<%= pkg.version %>.css'
			}
		},
		watch: {
		    js: {
		        files: jsFiles,
		        tasks: ['uglify', 'copy:js']
		    },
		    css: {
		        files: cssFiles,
		        tasks: ['cssmin', 'copy:css']
		    }
		}
    });

    grunt.registerTask('default', ['watch']);
    grunt.registerTask('build', ['uglify', 'cssmin', 'copy:js', 'copy:css']);

};