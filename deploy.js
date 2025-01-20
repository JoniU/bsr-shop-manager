require('dotenv').config();
const { execSync } = require('child_process');

const remoteSSH = process.env.REMOTE_SSH;
const remotePath = process.env.REMOTE_PATH;
const sshPort = process.env.SSH_PORT;
const localPath = process.cwd();

// Inline exclusions
const exclusions = [
    '.babelrc',
    '.env',
    '.DS_Store',
    '.git/',
    '.gitignore',
    'HEAD',
    'hooks/',
    'info/',
    'objects/',
    'refs/',
    'deploy.js',
    'package-lock.json',
    'package.json',
    'tsconfig.json',
    'webpack.config.js',
    'admin/src/',
    '*.log',
    'tmp/',
    'logs/',
    'node_modules/' // If ever present in the future
];

// Generate rsync exclude parameters
const excludeParams = exclusions.map((exclude) => `--exclude=${exclude}`).join(' ');

if (!remoteSSH || !remotePath || !sshPort) {
    console.error('Error: Missing required environment variables.');
    process.exit(1);
}

console.log(`Deploying from ${localPath} to ${remoteSSH}:${remotePath}`);
try {
    // Rsync command
    const rsyncCommand = `rsync -avz ${excludeParams} -e "ssh -p ${sshPort}" "${localPath}/" "${remoteSSH}:${remotePath}/"`;
    console.log(`Running command: ${rsyncCommand}`);
    execSync(rsyncCommand, { stdio: 'inherit' });

    // Post-deployment step: Regenerate product_data_cache.json
    console.log('Regenerating product_data_cache.json on the remote server...');
    const regenerateCommand = `ssh -p ${sshPort} ${remoteSSH} "php ${remotePath}/admin/generate-data-cache.php"`;
    execSync(regenerateCommand, { stdio: 'inherit' });

    console.log('Deployment and regeneration successful!');
} catch (error) {
    console.error('Deployment failed:', error.message);
}