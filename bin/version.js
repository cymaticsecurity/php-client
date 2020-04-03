'use strict';

let fs = require('fs');
let path = require('path');

function patchVersion() {
    let packageFilePath = path.join(process.cwd(), 'package.json');
    let clientFilePath = path.join(process.cwd(), 'Client.php');
    let pkg = require(packageFilePath);
    let plugin = fs.readFileSync(clientFilePath, {encoding: 'UTF-8'});
    let regex = / {0,}public {1,}static {1,}\$version {0,}= {0,}(['"])(?<version>[0-9.]+)(['"]) {0,};/gimu;
    let match = regex.exec(plugin);
    if (match && match.groups && match.groups.version && pkg.version !== match.groups.version) {
        console.log("Changed version from %s to %s", pkg.version, match.groups.version);
        pkg.version = match.groups.version;
        pkg.auto = pkg.auto || {
            version: {
                updated: null
            }
        };
        pkg.auto.version.updated = new Date();
        fs.writeFileSync(packageFilePath, JSON.stringify(pkg, null, '  '));
    } else if (!match || !match.groups || !match.groups.version) {
        console.error('Version is not found in "' + clientFilePath + '"');
        process.exit(2);
    }
}

try {
    console.log('Patching version in package.json..');
    patchVersion();
    console.log('Done.');
    process.exit(0);
} catch (e) {
    console.error(e);
    process.exit(1);
}
