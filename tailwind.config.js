/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php", // Look for Tailwind classes in all PHP files in the root
    "./src/**/*.{html,js,php}", // Adjust if you have other source folders
    "./node_modules/flowbite/**/*.js" // Crucial for Flowbite's classes
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('flowbite/plugin') // Add Flowbite plugin here
  ],
}