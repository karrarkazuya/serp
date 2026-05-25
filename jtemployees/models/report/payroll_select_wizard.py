from odoo import models, fields


class PayrollSelectWizard(models.TransientModel):
    _name = 'jtemployees.payroll.select.wizard'
    _description = 'Select Payroll Wizard'

    payroll_id = fields.Many2one(
        'jtemployees.payrolls',
        string='Payroll',
    )

    def action_apply(self):
        return {
            'type': 'ir.actions.act_window_close',
            'infos': {
                'payroll_id':   self.payroll_id.id,
                'payroll_name': self.payroll_id.name or '',
            },
        }
