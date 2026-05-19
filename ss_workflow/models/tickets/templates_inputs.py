# -*- coding: utf-8 -*-

from odoo import models, fields, api


class ssw_templates_inputs(models.Model):
    _name = 'ssw.tickets.templates.inputs'
    _description = 'Ticket Input'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title')
    
    template_id                   = fields.Many2one('ssw.tickets.templates', string='Template', required=True, tracking=True)
    type                          = fields.Selection([("char", 'Char'), ("int", 'Integer'), ("date", 'Date'), ("datetime", 'Datetime'), ("boolean", 'Boolean'), ("image", 'Image'), ("select", 'Select'), ("model", 'Other'), ("label", 'Label')], default='char', required=True, tracking=True)
    reference_model_name          = fields.Selection([("res.partner", 'Contact'), ("hr.employee", 'Employee'), ("jtfttha.agents", 'FTTH agent'), ("jtfttha.infra.region", 'FTTH region'), ("jtfttha.infra.project", 'FTTH project'), ("jtfttha.infra.exchange", 'FTTH exchange'), ("jtfttha.infra.ring", 'FTTH ring'), ("jtfttha.infra.fdt", 'FTTH FDT'), ("jtfttha.infra.olt", 'FTTH OLT'), ("jtfttha.infra.port", 'FTTH Port'), ("jtwirelessa.agents", 'Wireless agent'), ("jtwirelessa.repeaters", 'Wireless repeater/node'), ("jtwirelessa.repeaters.vlans", 'Wireless vlan'), ("jtwirelessa.repeaters.switches", 'Wireless switch'), ("jtwirelessa.repeaters.ports", 'Wireless port'), ("ssd.document", 'Document'), ("stock.picking", 'Inventory receipt/delivery/internal'), ("product.template", 'Product'), ("hr.expense.sheet", 'Expense'), ("account.payment", 'Payment'), ("account.move", 'Invoice')], default='res.partner', tracking=True)
    allow_multiple_model_values   = fields.Boolean(string='Allow multiple records', default=False, tracking=True)
    inputs_sub                    = fields.One2many('ssw.tickets.templates.inputs.subs', 'input_id', string='Subs', tracking=True)
    departments_can_view          = fields.Many2many(
        'ssw.departments',
        'ssw_tickets_dept_can_view_rel',  # Relation table name for this field
        'template_id',                 # Column for the current model
        'department_id',               # Column for the related model
        string='Allowed departments view',
        tracking=True
    )

    departments_can_change        = fields.Many2many(
        'ssw.departments',
        'ssw_tickets_dept_can_change_rel',  # Relation table name for this field
        'template_id',                   # Column for the current model
        'department_id',                 # Column for the related model
        string='Allowed departments change',
        tracking=True
    )
    deleted                       = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
