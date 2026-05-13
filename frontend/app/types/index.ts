export interface TimeEntry {
  id: string
  date: string
  project?: string
  subProject?: string
  description: string
  subDescription?: string
  furtherInfo?: string
  start?: string
  finish?: string
  duration: string
  durationMinutes: number
  logged?: boolean
  invoiced?: boolean
  clientId?: string
  task?: string
  invoiceId?: string | null
}

export interface Client {
  id: string
  name: string
  legalName?: string
  address?: string
  phone?: string
  email?: string
  tasks?: string[]
}

export interface Invoice {
  id: string
  number: string
  clientId: string
  createdDate: string
  dueDate: string
  rate: number
  subtotal: number
  taxRate: number
  taxAmount: number
  total: number
  status: 'draft' | 'sent' | 'paid' | 'void'
  notes?: string
  entryIds?: string[]
}

export interface CompanySetting {
  name: string
  address: string
  phone: string
  email: string
  logoUrl?: string | null
  defaultRate: number
  defaultTaxRate: number
}

export interface User {
  id: string
  name: string
  email: string
  email_verified_at: string | null
}

export interface AuthState {
  token: string | null
  user: User | null
}
