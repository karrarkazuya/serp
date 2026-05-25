from odoo import models, fields, api

class payrolls_details(models.Model):
    _name = 'jtemployees.payrolls.details'
    _description = 'Payroll details'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Text(
        string='Title',
        tracking=True
    )
    
    slug = fields.Char(
        string='Slug',
        tracking=True
    )
    
    payroll_slip = fields.Many2one('jtemployees.payrolls.slips', string='Payroll Slip', required=True, tracking=True)
    json_details = fields.Char(string='Json Details', required=True, tracking=True)
    amount = fields.Float(
        string='Total',
        required=True,
        tracking=True
    )
    
    subs                  = fields.One2many('jtemployees.payrolls.dsubs', inverse_name="details_id", string='Subs', required=True, tracking=True, domain=[('deleted', '=', False)])
    employee              = fields.Many2one('hr.employee', string='Employee', store=True, compute='_compute_employee', tracking=True)
    
    deleted               = fields.Boolean(string='Active', default=False, tracking=True)

    @api.depends("name")
    def _compute_employee(self):
        for record in self:
            record.employee = record.payroll_slip.employee.id
            
    def unlink(self):
        for record in self:
            record.sudo().write({'deleted': True})
        return True