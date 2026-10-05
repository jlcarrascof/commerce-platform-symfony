import type { Product } from '../types/product'

const electronics = { id: 1, name: 'Electronics', slug: 'electronics' }
const homeKitchen = { id: 2, name: 'Home & Kitchen', slug: 'home-kitchen' }
const sports = { id: 3, name: 'Sports & Outdoors', slug: 'sports-outdoors' }

const imageFor = (slug: string): string => `https://picsum.photos/seed/${slug}/400/300`

export const mockProducts: Product[] = [
  {
    id: 1,
    name: 'Wireless Mouse',
    slug: 'wireless-mouse',
    description: 'Ergonomic wireless mouse with USB receiver.',
    priceInCents: 2499,
    stock: 80,
    imageUrl: imageFor('wireless-mouse'),
    category: electronics,
  },
  {
    id: 2,
    name: 'Mechanical Keyboard',
    slug: 'mechanical-keyboard',
    description: 'RGB mechanical keyboard with blue switches.',
    priceInCents: 6999,
    stock: 45,
    imageUrl: imageFor('mechanical-keyboard'),
    category: electronics,
  },
  {
    id: 3,
    name: 'Non-stick Frying Pan',
    slug: 'non-stick-frying-pan',
    description: '28cm non-stick frying pan, induction compatible.',
    priceInCents: 1899,
    stock: 50,
    imageUrl: imageFor('non-stick-frying-pan'),
    category: homeKitchen,
  },
  {
    id: 4,
    name: 'Yoga Mat',
    slug: 'yoga-mat',
    description: 'Non-slip yoga mat with carrying strap.',
    priceInCents: 1999,
    stock: 65,
    imageUrl: imageFor('yoga-mat'),
    category: sports,
  },
  {
    id: 5,
    name: 'Insulated Water Bottle',
    slug: 'insulated-water-bottle',
    description: '750ml stainless steel insulated bottle.',
    priceInCents: 1799,
    stock: 100,
    imageUrl: imageFor('insulated-water-bottle'),
    category: sports,
  },
  {
    id: 6,
    name: 'Electric Kettle',
    slug: 'electric-kettle',
    description: '1.7L stainless steel electric kettle.',
    priceInCents: 2299,
    stock: 40,
    imageUrl: imageFor('electric-kettle'),
    category: homeKitchen,
  },
]
