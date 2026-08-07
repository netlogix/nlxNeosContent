export default class AsyncService {
    withTimeout(promise, ms = 15000, label = 'Request') {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                reject(new Error(`${label} timed out after ${ms}ms`));
            }, ms);
            promise.then(
                (value) => {
                    clearTimeout(timer);
                    resolve(value);
                },
                (error) => {
                    clearTimeout(timer);
                    reject(error);
                }
            );
        });
    }

    async withRetry(fn, retries = 1) {
        let lastError;
        for (let attempt = 0; attempt <= retries; attempt++) {
            try {
                return await fn();
            } catch (error) {
                lastError = error;
            }
        }
        throw lastError;
    }
}
