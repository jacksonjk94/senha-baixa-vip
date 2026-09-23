import axios from 'axios';

// Adiciona o cabeçalho X-Inertia a todas as requisições
axios.defaults.headers.common['X-Inertia'] = 'true';

// Configura um interceptor de resposta para lidar com redirecionamentos Inertia
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (
            error.response?.status === 409 &&
            error.response.headers['x-inertia-location']
        ) {
            // Se houver um conflito e um redirecionamento Inertia, força a navegação para a nova localização
            // window.location.href = error.response.headers['x-inertia-location'];
        } else {
            return Promise.reject(error);
        }
    },
);

export default axios;
