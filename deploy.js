require('dotenv').config();
const { execSync } = require('child_process');
const path = require('path');

const remoteSSH = process.env.REMOTE_SSH;
const remotePath = process.env.REMOTE_PATH;
const sshPort = process.env.SSH_PORT;
const localPath = '/Users/joni/blacksmokeracing.com/wp-content/plugins/bsr-shop-manager/';

// Inline exclusions
const exclusionsFile = '/Users/joni/blacksmokeracing.com/wp-content/plugins/bsr-shop-manager/deploy-exclusions.txt';

if (!remoteSSH || !remotePath || !sshPort) {
    console.error('Error: Missing required environment variables.');
    process.exit(1);
}

console.log(`Deploying from ${localPath} to ${remoteSSH}:${remotePath}/bsr-shop-manager`);
try {
    // Simulate deployment to debug file inclusion
    const dryRunCommand = `rsync -avz --progress --delete --ignore-times --dry-run --exclude-from="${exclusionsFile}" -e "ssh -p ${sshPort}" "${localPath}/" "${remoteSSH}:${remotePath}/bsr-shop-manager"`;
    console.log(`Simulating command: ${dryRunCommand}`);
    execSync(dryRunCommand, { stdio: 'inherit' });

    // Actual deployment
    console.log('Dry run completed. Running actual deployment...');
    const rsyncCommand = `rsync -avz --progress --delete --ignore-times --exclude-from="${exclusionsFile}" -e "ssh -p ${sshPort}" "${localPath}/" "${remoteSSH}:${remotePath}/bsr-shop-manager"`;
    console.log(`Running command: ${rsyncCommand}`);
    execSync(rsyncCommand, { stdio: 'inherit' });

    console.log('Deployment successful!');
} catch (error) {
    console.error('Deployment failed:', error.message);
}
