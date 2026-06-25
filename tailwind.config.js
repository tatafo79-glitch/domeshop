/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/templates/**/*.{ts, scss}",
    "./src/templates/**/*.html", // PHP 템플릿 파일에서 쓰는 클래스도 감지해야 함
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}