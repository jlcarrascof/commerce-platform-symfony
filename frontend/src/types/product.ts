export interface Product {
  id: number
  name: string
  slug: string
  description: string
  priceInCents: number
  stock: number
  imageUrl: string
  category: {
    id: number
    name: string
    slug: string
  }
}
