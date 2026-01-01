module.exports = {
    apps: [
        {
            name: 'vite-build-watch',
            script: 'npm',
            args: 'run watch',
            cwd: './',
            watch: false,
            autorestart: true,
            env: {
                NODE_ENV: 'production',
            },
        },
    ],
};
