import postcssNesting from 'postcss-nesting';
import tailwindPostcss   from '@tailwindcss/postcss';
import autoprefixer from 'autoprefixer';

export default {
  plugins: [
    postcssNesting,
    tailwindPostcss(),
    autoprefixer,
  ],
};
