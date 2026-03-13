# Wrecked Tech - Review and Analytics Testing Site

## Project Overview
A deliberately flawed e-commerce site for "Wrecked Tech" - a company selling damaged/refurbished technology at discount prices. The site should contain common web development mistakes and performance issues to generate interesting analytics data.

## Core Pages

### 1. Homepage (index.html)
- Hero banner with auto-playing video (no controls)
- Product carousel that loads all images at once
- Newsletter signup form with no validation
- Footer with broken social media links
- Implement rage click detection areas

### 2. Product Listing (/products.html)
- Infinite scroll that doesn't virtualize
- Filter system that reloads entire page
- Images of varying sizes causing layout shift
- Sort dropdown that sometimes doesn't work
- Memory leak from event listeners not being cleaned up

### 3. Product Detail (/product-detail.html)
- Image zoom that breaks on mobile
- Add to cart button that double-submits
- Reviews section that loads synchronously
- Related products that block page render
- Stock counter that uses setInterval incorrectly

### 4. Checkout (/checkout.html)
- Multi-step form that loses state on back button
- Payment form with unclear error messages
- Shipping calculator with race conditions
- Order summary that doesn't update properly
- Submit button without loading states

## Technical Issues to Implement

### Performance Problems
- No lazy loading on images
- Blocking scripts in document head
- Large unoptimized images (use data URIs for some)
- No caching headers
- Synchronous XHR requests
- CSS with expensive selectors
- Forced reflows in loops

### JavaScript Errors
- Undefined variable references
- Type errors from assuming data shapes
- Unhandled promise rejections
- Console errors from missing DOM elements
- Race conditions in async operations
- Memory leaks from closures
- Circular references

### UX Issues
- Buttons too small for mobile (under 44px)
- Low contrast text (#777 on #fff)
- Forms without labels
- Missing focus indicators
- Horizontal scroll on mobile
- Modal dialogs without escape key handling
- Broken back button behavior


## File Structure

```
wrecked-tech/
├── index.html
├── products.html
├── product-detail.html
├── checkout.html
├── css/
│   └── styles.css (one massive file or many many files)
├── js/
│   ├── main.js (shared functionality)
│   └── [page-specific].js
└── assets/
└── [images and images as data URIs where possible]
```

## Implementation Notes

1. **Usability Issues**
   - Make sure that blue or other affordances are used in a way that it shouldn't be making something look clickable which isn't
   - Make sure that things are unclear in forms such as formats expected.
   - Add a reset / clear button that doesn't warn the user.

2. **Error Generation**:
	- Add a `chaos.js` module that randomly triggers errors
	- Include edge cases like dividing by zero
	- Access properties of null/undefined
	- Infinite loops that get caught by browser

3. **Performance Degradation**:
	- Add artificial delays with setTimeout
	- Use nested loops for simple operations
	- Parse large JSON strings repeatedly
	- Animate using JavaScript instead of CSS

4. **Mobile Issues**:
	- Use viewport units incorrectly
	- Touch events that don't prevent default
	- Fixed positioning that breaks on keyboard open
	- Orientation change handling bugs

## Brand Style
- Colors: Black (#000), Warning Yellow (#FFD700), Error Red (#FF0000)
- Font: System fonts with fallback issues
- Logo: "WT" in glitchy, broken style
- Tagline: "Quality Issues, Quality Prices"

## Sample Products
- "Slightly Burnt Graphics Card" - $50
- "Laptop with Missing Keys" - $200
- "Phone with Cracked Screen" - $75
- "Router that Only Works on Tuesdays" - $15
- "Mouse with Inverted Scroll" - $5

