export type OrderStatus = 'pending' | 'confirmed' | 'cancelled'

export interface OrderItem {
  productId: number
  productName: string
  quantity: number
  unitPriceInCents: number
}

export interface Order {
  id: number
  status: OrderStatus
  createdAt: string
  customer: {
    id: number
    fullName: string
  }
  items: OrderItem[]
}
