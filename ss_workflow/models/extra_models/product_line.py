# -*- coding: utf-8 -*-

from odoo import api, fields, models, _
from odoo.exceptions import UserError
from odoo.osv import expression


class ProductLine(models.Model):
    _name = 'ssw.product.line'
    _description = 'Product Line'

    _check_company_auto = True

    ssw_task_input_id = fields.Many2one('ssw.proc.tasks.inputs', required=True)
    description = fields.Char(
        "Description", required=True,
        compute="_compute_description", store=True, readonly=False, precompute=True)
    company_id = fields.Many2one(
        'res.company',
        string='Company',
        required=True,
        default=lambda self: self._default_company_id(),
        index=True,
    )
        
    product_id = fields.Many2one('product.product', required=True, domain=lambda self: self._domain_product_id())

    product_uom_category_id = fields.Many2one(
        'uom.category',
        related='product_id.product_tmpl_id.uom_id.category_id',
        store=True,
        readonly=True
    )
    product_uom_id = fields.Many2one(
        'uom.uom', string="Unit of Measure",
        compute="_compute_product_uom_id", store=True, readonly=False, precompute=True,
        domain="[('category_id', '=', product_uom_category_id)]")
    quantity = fields.Float("Quantity", default=1.0)
    
    po_uom_qty = fields.Float(
        "Purchase UoM Quantity", compute='_compute_po_uom_qty',
        help="The quantity converted into the UoM used by the product in Purchase Order.")
    purchase_order_line_id = fields.Many2one('purchase.order.line')
    
    warehouse_id = fields.Many2one(
        'stock.warehouse',
        string="Warehouse",
        default=lambda self: self._default_warehouse_id(),
        domain="[('company_id', '=', company_id)]",
        check_company=True,
    )
    
    
    @api.depends('product_id')
    def _compute_description(self):
        for line in self:
            line.description = line.product_id.description_purchase or line.product_id.display_name

    @api.depends('product_id')
    def _compute_product_uom_id(self):
        for line in self:
            line.product_uom_id = line.product_id.uom_id

    @api.depends('product_uom_id', 'quantity')
    def _compute_po_uom_qty(self):
        for line in self:
            if line.product_id and line.quantity:
                uom = line.product_uom_id or line.product_id.uom_id
                line.po_uom_qty = uom._compute_quantity(
                    line.quantity,
                    line.product_id.uom_po_id
                )
            else:
                line.po_uom_qty = 0.0

    def _domain_product_id(self):
        """ Filters on product to get only the ones who are available on
        purchase in the case the approval request type is purchase. """
        return [('purchase_ok', '=', True)]
        
        
    def _get_seller_id(self):
        self.ensure_one()
        res = self.env['product.supplierinfo']
        if self.product_id and self.po_uom_qty:
            res = self.product_id.with_company(self.company_id)._select_seller(
                quantity=self.po_uom_qty,
                uom_id=self.product_id.uom_po_id,
            )
        return res

    def _check_products_vendor(self):
        """ Raise an error if at least one product requires a seller. """
        product_lines_without_seller = self.filtered(lambda line: not line._get_seller_id())
        if product_lines_without_seller:
            product_names = product_lines_without_seller.product_id.mapped('display_name')
            raise UserError(
                _('Please set a vendor on product(s) %s.', ', '.join(product_names))
            )
            
    def _default_company_id(self):
        company_id = self.env.context.get('default_company_id') or self.env.company.id
        return self.env['res.company'].browse(company_id)

    def _default_warehouse_id(self):
        company_id = self.env.context.get('default_company_id') or self.env.company.id
        warehouse = self.env['stock.warehouse'].search(
            [('company_id', '=', company_id)], limit=1
        )
        return warehouse

    @api.model_create_multi
    def create(self, vals_list):
        for vals in vals_list:
            if vals.get('ssw_task_input_id') and not vals.get('company_id'):
                input_record = self.env['ssw.proc.tasks.inputs'].browse(vals['ssw_task_input_id'])
                vals['company_id'] = input_record.company_id.id or self.env.company.id
        records = super().create(vals_list)
        records._update_task_context()
        return records

    def write(self, vals):
        if not vals.get('company_id') and ('warehouse_id' in vals or 'ssw_task_input_id' in vals):
            vals = dict(vals)
            if vals.get('ssw_task_input_id'):
                input_record = self.env['ssw.proc.tasks.inputs'].browse(vals['ssw_task_input_id'])
                vals['company_id'] = input_record.company_id.id or self.env.company.id
            elif len(self) == 1 and not self.company_id:
                vals['company_id'] = self.ssw_task_input_id.company_id.id or self.env.company.id
        result = super().write(vals)
        self._update_task_context()
        return result

    def _get_picking_type(self):
        """ Returns the picking type for incoming picking, depending of the
            product line warehouse. """
        self.ensure_one()
        if not self.warehouse_id:
            return None
        return self.warehouse_id.in_type_id

    def _get_purchase_orders_domain(self, vendor):
        """ Return a domain to get purchase order(s) where this product line could fit in.

        :return: list of tuple.
        """
        self.ensure_one()
        domain = [
            ('company_id', '=', self.company_id.id),
            ('partner_id', '=', vendor.id),
            ('state', '=', 'draft'),
        ]
        picking_type = self._get_picking_type()
        if picking_type:
            domain = expression.AND([
                domain,
                [('picking_type_id', '=', picking_type.id)]
            ])
        return domain

    def _get_purchase_order_values(self, vendor):
        """ Get some values used to create a purchase order.
        Called in approval.request `action_create_purchase_orders`.

        :param vendor: a res.partner record
        :return: dict of values
        """
        self.ensure_one()
        vals = {
            'origin': self.ssw_task_input_id.name,
            'partner_id': vendor.id,
            'company_id': self.company_id.id,
            'payment_term_id': vendor.property_supplier_payment_term_id.id,
            'fiscal_position_id':self.env['account.fiscal.position']._get_fiscal_position(vendor).id,
        }
        picking_type = self._get_picking_type()
        if picking_type:
            vals['picking_type_id'] = picking_type.id
        return vals

    def _update_task_context(self):
        for input_record in self.mapped('ssw_task_input_id'):
            input_record.task_id.updateContext()

    def unlink(self):
        inputs = self.mapped('ssw_task_input_id')
        result = super().unlink()
        for input_record in inputs:
            input_record.task_id.updateContext()
        return result
